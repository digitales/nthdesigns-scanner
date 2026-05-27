# Audit backfill — Design Spec

**Date:** 2026-05-27  
**Status:** Implemented  
**Scope:** Artisan command to find prospects with finished audit status but missing audit payloads, re-queue full audit pipeline with throttled dispatches.

**Approach:** Reuse existing jobs (`AuditSiteJob` → `CombineScoresJob` → `GenerateProspectReportJob` → `CaptureScreenshotJob`). No new orchestrator job. Supersedes the plan-completion decision to avoid historical backfill for audit *data* (that spec covered scoring-only re-combine).

---

## Goal

After changes to `scripts/audit.js` or audit infrastructure (e.g. Fly browser service, `ScannerConfig` runtime driver), operators need a safe, repeatable way to re-run audits for prospects that previously reached `complete` or `failed` but stored incomplete raw payloads or Lighthouse performance data — without creating a new search.

---

## Decisions

| Topic | Decision |
|-------|----------|
| Incomplete definition | Option B: finished status but missing audit data (see selection rules) |
| Search scope | Only prospects whose search `status` ∈ `auditing`, `complete` |
| Downstream pipeline | Full: audit → combine → report → screenshot (when website present) |
| Execution | Queue dispatches to auditing queue; stagger with `--delay` (default 5s) |
| Safety default | Dry-run unless `--execute` |
| Command name | `scanner:backfill-audits` |
| Job changes | None — reset `audit_status` to `pending` and clear stale audit fields before dispatch |
| Historical scoring-only backfill | Out of scope — this command re-runs the audit script, not combine-only |

---

## Selection rules

A prospect is **incomplete** when **all** of the following hold:

1. Related search `status` ∈ `auditing`, `complete`
2. Related search `scan_type` ∈ `accessibility_only`, `combined`
3. `website_url` is not null and not empty
4. `audit_status` ∈ `complete`, `failed`
5. **Not** `audit_status = skipped` when `config('scanner.audit_driver') === 'skip'` (intentional driver skip)
6. **Not** `audit_status = pending` (already in flight or stuck — fix separately)
7. **Any** incomplete data signal:
   - `raw_a11y_payload` is null, **or**
   - `raw_lighthouse_payload` is null, **or**
   - `performance_score = 0` **and** `raw_lighthouse_payload` is null

`gbp_only` searches are excluded. Prospects without a website are excluded.

### Optional CLI filters

| Flag | Effect |
|------|--------|
| `--search=ID` | Limit to one search |
| `--prospect=ID` | Single prospect (must still pass selection rules unless documented otherwise) |
| `--limit=N` | Cap dispatches per run (default: no cap) |
| `--delay=N` | Seconds between dispatches (default: `5`) |

---

## Command behaviour

### Signature

```bash
php artisan scanner:backfill-audits [--execute] [--search=] [--prospect=] [--limit=] [--delay=5]
```

### Default (dry-run)

- Run `IncompleteAuditQuery` (class or `Prospect` scope)
- Print count and table: `prospect_id`, `business_name`, `search_id`, `audit_status`, `reason`
- No database writes, no queue dispatches
- Exit `0`; message `No incomplete audits found` when empty

### `--execute`

For each selected prospect (respecting `--limit`):

1. Update prospect:
   - `audit_status` → `pending`
   - `raw_a11y_payload` → null
   - `raw_lighthouse_payload` → null
   - `a11y_score` → 0
   - `a11y_flags` → null
   - `performance_score` → 0
   - Do **not** modify GBP fields, `combined_score`, `dominant_angle`, or `raw_gbp_payload`
2. Dispatch `AuditSiteJob::dispatch($prospect)->delay(now()->addSeconds($index * delay))`
3. Print summary: `Found N, dispatched N`

Existing job chain handles the rest:

- `AuditSiteJob` → `CombineScoresJob` → `GenerateProspectReportJob` → `CaptureScreenshotJob` (if website)

### Throttle note

`--delay` staggers **enqueue** time only. Worker concurrency is still determined by auditing queue workers and Fly capacity. Operators increase `--delay` if the browser service shows rate limits or timeouts.

---

## Edge cases

| Case | Behaviour |
|------|-----------|
| `audit_driver === 'skip'` | Exclude `audit_status = skipped` from selection |
| `failed` with missing payloads | Included |
| `failed` with partial payloads | Included if any incomplete rule matches |
| Search `status` not in scope | Excluded |
| Expired prospect (`expires_at` past) | Still eligible; purge command handles expiry separately |
| `--limit` smaller than match count | Dispatch first N (stable order by `prospect.id`); output notes remaining count |
| Audit job fails after execute | `audit_status` → `failed`; operator fixes infra and re-runs command |
| No matches | Exit 0, informational message |

---

## Operations (Laravel Cloud)

Run on an **app** instance (scheduler/web), not necessarily on the auditing worker:

```bash
# Preview
php artisan scanner:backfill-audits

# Run (example: one search, capped batch)
php artisan scanner:backfill-audits --execute --search=42 --limit=50 --delay=5
```

Prerequisites:

- Auditing queue workers running
- `AUDIT_SERVICE_URL` set so `config('scanner.audit_driver')` resolves to `http` on workers
- Fly browser service healthy

Document in `docs/deployment/laravel-cloud.md` under **Failed audits** (replace manual “reset to pending and re-dispatch” with this command).

---

## Implementation

### New files

| File | Role |
|------|------|
| `app/Console/Commands/BackfillAuditsCommand.php` | CLI, dry-run table, execute path |
| `app/Support/IncompleteAuditQuery.php` | Query builder / static entry returning eligible prospects with `reason` |

Laravel auto-discovers the command via `app/Console/Commands/`.

### No changes required

- `AuditSiteJob`, `CombineScoresJob`, `GenerateProspectReportJob`, `CaptureScreenshotJob` — existing guards work after reset to `pending`

---

## Testing

### `IncompleteAuditQuery` (unit)

| Fixture | Expect |
|---------|--------|
| `complete`, null `raw_lighthouse_payload`, combined scan | Match |
| `complete`, `performance_score = 0`, lighthouse JSON present | No match |
| `skipped`, `audit_driver = skip` | No match |
| Search `status = failed` | No match |
| `gbp_only` scan | No match |
| `pending` | No match |

### `BackfillAuditsCommand` (feature)

- Dry-run: no DB changes, `Queue::fake()` → no jobs pushed
- `--execute`: prospect `audit_status` pending, payloads nulled, `AuditSiteJob` pushed to auditing connection/queue

---

## Out of scope

- Combine-only or report-only backfill without re-running `scripts/audit.js`
- Automatic retry loops or scheduled backfill
- Changing `audit_jobs` history rows
- Re-scoring prospects that have complete payloads but old scoring algorithm (use a separate command or one-off combine dispatch)
