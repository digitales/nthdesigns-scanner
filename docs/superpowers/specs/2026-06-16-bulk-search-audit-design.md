# Bulk search audit — Design Spec

**Date:** 2026-06-16  
**Status:** Approved  
**Scope:** Bulk site-audit actions on `/searches/{id}` for selected prospect rows — re-audit failed rows, or force re-audit all selected rows with a website URL.

**Approach:** New search-scoped `POST /searches/{search}/bulk-audit` endpoint and `BulkProspectAuditService`, reusing `ProspectAuditService`, `StaleAuditJobCloser`, and `QueueDispatchDelay` (same patterns as `scanner:repair-audits` and single-prospect `POST /prospects/{id}/audit`).

---

## Goal

Operators reviewing search results often need to rework site audits that failed (timeout, site unreachable, audit runner error). Today they must open each prospect detail page and click "Re-run site audit" one at a time.

Add two bulk toolbar actions on the search results page when rows are selected:

1. **Re-audit failed** — queue site audits only for selected rows with `audit_status === failed` and a `website_url`.
2. **Force re-audit** — queue site audits for all selected rows with a `website_url`, including complete rows and pending rows (restart via repair path).

GBP scores, flags, and `raw_gbp_payload` remain unchanged. Reports are not auto-regenerated (`suppress_auto_report: true`, matching single re-audit).

---

## Decisions

| Topic | Decision |
|-------|----------|
| Endpoint | `POST /searches/{search}/bulk-audit` with `{ prospect_ids, mode: 'failed' \| 'force' }` |
| Failed mode eligibility | `audit_status === failed` + has `website_url`; skip pending, complete, no URL |
| Force mode eligibility | Has `website_url`; includes failed, complete, and pending |
| Pending rows (failed mode) | Skipped; counted in flash summary |
| Pending rows (force mode) | Restart via `StaleAuditJobCloser` + `repairSiteAudit()` |
| Non-pending rows | `queueSiteAudit()` — same as `POST /prospects/{id}/audit` |
| Phase gating | Disabled during `queued` and `discovering`; enabled during `auditing` and `complete` |
| Selection after submit | Cleared (same as bulk outreach) |
| Staggering | `QueueDispatchDelay::forIndex()` using `config('scanner.audit_dispatch_stagger_seconds')` |
| SQS batch cap | Queue up to `maxJobsPerBatch()`; flash notes if remainder needs re-run |
| Authorization | Search owner + per-prospect `view` policy; reject if any ID not in search |
| GBP-only scans | Allowed — `queueSiteAudit()` has no scan-type guard (matches single re-audit) |
| Auto-report | Not regenerated (`suppress_auto_report: true`) |

---

## Architecture

### New components

| Component | Responsibility |
|-----------|----------------|
| `BulkProspectAuditRequest` | Validate `prospect_ids` (required array, min 1, each exists) and `mode` (`failed` \| `force`) |
| `SearchController::bulkAudit()` | Authorize search, phase guard, delegate to service, flash result |
| `BulkProspectAuditService` | Filter eligibility, authorize prospects, dispatch with stagger, return counts |
| `BulkAuditResult` (DTO/value object) | Structured `{ queued, skipped: { pending, no_url, not_failed, not_in_search }, truncated }` |
| `Search/Show.jsx` | Two toolbar buttons, derived eligible counts, `router.post` handler |

### Reused (unchanged)

| Component | Use |
|-----------|-----|
| `ProspectAuditService::queueSiteAudit()` | Failed and complete rows |
| `ProspectAuditService::repairSiteAudit()` | Pending rows in force mode |
| `StaleAuditJobCloser::closeRunning()` | Before repair dispatch on pending rows |
| `AuditSiteJobDispatch` | Staggered job queueing |
| `QueueDispatchDelay` | Index-based delay + SQS cap |
| `useProgressReload` | Existing polling shows re-queued rows as pending |

### Route

```php
Route::post('/searches/{search}/bulk-audit', [SearchController::class, 'bulkAudit'])
    ->name('searches.bulk-audit');
```

---

## User flow

1. Operator opens `/searches/{id}` and selects rows via checkboxes (or select-all on filtered view).
2. When search phase is `auditing` or `complete`, toolbar shows:
   - `Re-audit N failed` (enabled when N > 0)
   - `Force re-audit N` (enabled when N > 0)
3. Operator clicks one action.
4. Server validates, queues eligible audits with staggered delays, redirects back with flash summary.
5. Selection clears. Page polls; re-queued rows show pending status and update as audits complete.

**Phase blocked:** During `queued` or `discovering`, both buttons are disabled with tooltip: *"Wait until discovery finishes before bulk re-auditing."*

---

## Eligibility matrix

| Row state | `failed` mode | `force` mode | Dispatch method |
|-----------|---------------|--------------|-----------------|
| `failed` + URL | Queue | Queue | `queueSiteAudit()` |
| `complete` + URL | Skip (not failed) | Queue | `queueSiteAudit()` |
| `pending` + URL | Skip | Queue | `StaleAuditJobCloser` + `repairSiteAudit()` |
| No URL | Skip | Skip | — |

Skip reasons are aggregated for the flash message.

---

## Flash messages

Examples:

- `Queued 8 site audits. Skipped 4 (2 pending, 2 no website).`
- `Queued 12 site audits.`
- `Queued 180 site audits. 20 not queued — run again to queue the rest.` (SQS cap hit)

---

## Frontend (`Search/Show.jsx`)

Add to existing `PageHeader` `actions` when `selectedIds.length > 0`:

```jsx
<Button kind="secondary" size="sm" disabled={failedEligibleCount === 0 || !canBulkAudit} …>
  Re-audit {failedEligibleCount} failed
</Button>
<Button kind="secondary" size="sm" disabled={forceEligibleCount === 0 || !canBulkAudit} …>
  Force re-audit {forceEligibleCount}
</Button>
```

**Derived state from `selectedProspects`:**

- `canBulkAudit` — phase is `auditing` or `complete`
- `failedEligibleCount` — selected + `audit_status === 'failed'` + `website_url`
- `forceEligibleCount` — selected + `website_url`

**Submit:**

```js
router.post(`/searches/${search.id}/bulk-audit`, {
  prospect_ids: selectedIds.map(Number),
  mode,
}, { preserveScroll: true, onSuccess: () => setSelected({}) });
```

No confirmation dialog.

---

## Error handling

| Condition | Response |
|-----------|----------|
| Search phase `queued` or `discovering` | 422 with message |
| `prospect_id` not in search | 422 |
| Unauthorized user | 403 |
| Empty eligible set after filtering | Redirect with flash: `No site audits queued. Skipped N (reasons).` |
| Zero `prospect_ids` after validation | Prevented by request validation |

---

## Testing

**Feature tests** (`BulkProspectAuditTest`):

| Test | Asserts |
|------|---------|
| Owner can bulk re-audit failed rows | `AuditSiteJob` pushed, audit fields reset, GBP unchanged |
| Force mode re-audits complete rows | `AuditSiteJob` pushed |
| Force mode restarts pending rows | Repair path used, stale job closed |
| Failed mode skips pending | Not queued, skip count in flash |
| Skips rows without URL | Not queued |
| Rejects prospect_ids from another search | 422 |
| Rejects during discovering phase | 422 |
| Other user forbidden | 403 |
| Staggered dispatch | Jobs receive incremental delay |

---

## Out of scope

- Per-row "Retry" icon in search results table actions (prospect detail already has re-audit)
- "Show failed only" filter preset
- Bulk dispatch progress modal
- Auto-regenerate reports after re-audit
- Screenshot-only repair (handled by `scanner:repair-audits` CLI)

---

## References

- Single re-audit: `POST /prospects/{prospect}/audit` → `ProspectController::reauditSite()`
- Repair CLI: `scanner:repair-audits` → `RepairAuditsCommand`
- Bulk selection pattern: `POST /outreach/selections`
- Search results page: `resources/js/Pages/Search/Show.jsx`
