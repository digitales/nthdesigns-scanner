# Site unreachable preflight — Design Spec

**Date:** 2026-06-12  
**Status:** Implemented  
**Scope:** Fast-fail HTTP preflight before heavy site scans; `audit_status: failed` with operator-visible "Site unreachable" label; GBP scoring preserved on combined scans; extensible gate for future structured data / JSON-LD scans.

**Approach:** Shared `WebsiteReachabilityService` + `SiteScanPreflightGate` called from `AuditSiteJob` before Fly/browser audit work. Permanent DNS/connection errors fail immediately; timeouts and 5xx retry briefly then fail. Unreachable prospects skip heavy scans, combine GBP-only scores, and surface clearly in UI and repair flows.

---

## Goal

Some prospect website URLs are dead, expired, or mis-typed (e.g. `ERR_NAME_NOT_RESOLVED`). Today:

1. **Accessibility audit** often completes with a soft load error (`audit_status: complete`, fallback a11y score 50) — not actionable enough for operators.
2. **Screenshot capture** throws hard `RuntimeException`, retries 3×, and lands in `failed_jobs` even when the audit already knew the site was down.

Operators need:

- **Fast fail** before expensive Playwright + axe + Lighthouse on Fly
- **`audit_status: failed`** so the prospect is reviewable and re-runnable after URL fix
- **Clear "Site unreachable" label** in search and prospect UI
- **GBP scores preserved** on combined scans (prospect stays in results)
- **One shared gate** for future in-depth scans (structured data, JSON-LD) without duplicating preflight logic

---

## Decisions

| Topic | Decision |
|-------|----------|
| Preflight mechanism | Laravel HTTP GET from queue worker (~5–10s), not Fly browser |
| Permanent failures | DNS / unresolvable host / connection refused → fail immediately, no retries |
| Transient failures | Timeout, 5xx, connection reset → up to 2 retries (~2s backoff), then fail |
| On unreachable | `audit_status: failed`, skip Fly audit, CMS fallback, screenshot |
| Combined scan GBP | Score GBP normally; dispatch `CombineScoresJob` with GBP-weighted combined score |
| Report generation | No auto-report when `audit_status: failed` (unchanged) |
| Re-audit | Existing `ProspectAuditService::queueSiteAudit()` / `repairSiteAudit()` resets fields and re-runs gate + audit |
| Legacy soft load errors | Existing `complete` + `raw_a11y_payload.error` rows unchanged; new scans use failed preflight path |
| Future scans | Preflight is prospect-level gate; structured data job added later behind same gate |
| Orchestrator job | Deferred until a second scan type ships; gate extracted as reusable class now |

---

## Architecture

### New components

| Component | Responsibility |
|-----------|----------------|
| `WebsiteReachabilityService` | HTTP GET with redirects; classify permanent vs transient errors; configurable timeout/retries |
| `ReachabilityResult` | Value object: `isReachable()`, `failureMessage()`, `isPermanent()` |
| `SiteScanPreflightGate` | Run check; on failure record `audit_jobs` + prospect fields + dispatch `CombineScoresJob`; on success return control to caller |
| `SiteScanFailureRecorder` | Shared persistence for gate failures (prospect update, `AuditErrorRecorder`, `AuditJobType::Accessibility` row) |

### Call flow (this implementation)

```text
ScorePlaceJob / DirectUrlScanJob
  └── AuditSiteJob
        1. SiteScanPreflightGate::passOrFail(prospect)
             ├── unreachable → record failure, CombineScoresJob, return (no throw)
             └── reachable → continue
        2. AuditRunnerService::run()  (existing)
        3. score, CMS, CombineScoresJob  (existing)
```

### Future call flow (documented, not built)

```text
ScorePlaceJob / DirectUrlScanJob
  └── SiteScanOrchestratorJob
        1. SiteScanPreflightGate (once)
        2. if unreachable → stop
        3. dispatch enabled jobs from config registry:
             - AuditSiteJob (accessibility)
             - StructuredDataScanJob (future)
```

### Scan registry (future config sketch)

```php
// config/scanner.php — illustrative
'site_scans' => [
    'accessibility' => ['job' => AuditSiteJob::class, 'enabled' => true],
    // 'structured_data' => ['job' => StructuredDataScanJob::class, 'enabled' => false],
],
```

Preflight stays on the Laravel worker. Each heavy scan may add Fly endpoints (`/audit`, `/detect-cms`, future `/structured-data`) without changing the gate.

---

## Preflight behaviour

### HTTP probe

- Method: `GET` (HEAD optional optimisation — use GET for broader server compatibility)
- Follow redirects (max 5)
- User-Agent: identifiable scanner agent string (configurable)
- Timeouts: connect ~5s, total request ~10s (configurable)
- Success: HTTP 2xx or 3xx after redirects
- Do **not** require valid HTML body — only proof the host responds

### Error classification

| Error class | Examples | Retries |
|-------------|----------|---------|
| **Permanent** | DNS failure, `ERR_NAME_NOT_RESOLVED` equivalent, connection refused, invalid host, SSL host mismatch | 0 |
| **Transient** | Request timeout, 5xx, connection reset, empty response timeout | Up to `site_preflight_retries` (default 2) with ~2s backoff |

Classification uses exception types / HTTP status / message heuristics in `WebsiteReachabilityService`. When ambiguous, prefer transient (one retry) over false permanent.

### Config (`config/scanner.php`)

| Key | Default | Purpose |
|-----|---------|---------|
| `site_preflight_enabled` | `true` | Kill switch |
| `site_preflight_timeout` | `10` | Total request timeout (seconds) |
| `site_preflight_connect_timeout` | `5` | Connect timeout (seconds) |
| `site_preflight_retries` | `2` | Transient retries only |

---

## Failure persistence

When preflight fails terminally:

### `audit_jobs`

- `job_type`: `accessibility`
- `status`: `failed`
- `error_message`: summarised via `AuditErrorRecorder` (e.g. `DNS resolution failed for franklynandco.uk`)
- Full body in `audit_job_error_details`

### Prospect fields

| Field | Value |
|-------|-------|
| `audit_status` | `failed` |
| `a11y_score` | `0` |
| `a11y_flags` | `['Site unreachable']` |
| `performance_score` | `0` |
| `raw_a11y_payload` | `{ url, error, preflight_failed: true, violations: [], lighthouse: null }` |
| `raw_lighthouse_payload` | `null` |
| `cms_detection` | unchanged / null |

### Downstream

- Dispatch `CombineScoresJob` (do not throw — expected outcome)
- `SearchStatusService::refresh()` — `failed` counts as finished; search can reach `complete`
- **Do not** dispatch `CaptureScreenshotJob` (no report path for failed audits today)
- **Do not** run CMS fallback

### `CombineScoresJob` adjustment

When combining for a prospect with `preflight_failed: true` in payload (or helper `Prospect::siteUnreachable()`):

- Run `combineForProspect()` so `combined_score` / `dominant_angle` reflect GBP (and zero a11y/perf)
- **Preserve** `audit_status: failed` — do not promote to `complete`

---

## Scoring

| Scenario | `a11y_score` | `combined_score` (combined scan) |
|----------|--------------|----------------------------------|
| Preflight failed | `0` | GBP-weighted formula with a11y=0, perf=0 |
| Legacy load error in audit (`complete`) | `50` (unchanged) | Existing behaviour |
| Successful audit | Violation-based | Full combined formula |

Flag copy: **`Site unreachable`** (distinct from legacy **`Site audit failed to load`**).

---

## UI and API

### Search results (`Search/Show.jsx`)

| State | Display |
|-------|---------|
| `audit_status: failed` + preflight | Row `failed` styling; status chip **Site unreachable** (or **Audit failed** with subtitle — prefer dedicated chip when `preflight_failed`) |
| URL column title | `audit_error` or `site_load_error` message |
| GBP / combined columns | Show scores normally |

Differentiate from legacy `siteLoadFailed` (`complete` + payload error): that path keeps existing "Site failed to load" treatment.

### Prospect detail (`Prospect/Show.jsx`)

When `audit_status: failed` and preflight:

- Banner: **Site unreachable** — fix website URL and re-run site audit
- Show stored error message from latest failed accessibility `audit_job`
- Existing re-run site audit control unchanged

### Site audit section (`SiteAuditSection.jsx`)

When report exists with legacy load error only — unchanged.

For failed preflight without report: section may be absent or show GBP-only context; no fake fallback score messaging.

### Progress flow (`ProgressFlowService`)

When `audit_status: failed` and `preflight_failed`:

- `current_step`: `a11y` (or new step `unreachable` if added)
- `status_message`: **Site unreachable** (not "Running accessibility audit")

### API / MCP (`SearchProspectResource`)

Existing fields:

- `audit_status`: `failed`
- `audit_error`: from failed `audit_jobs.error_message`
- `site_load_error`: from `raw_a11y_payload.error` when present

Optional additive field (nice-to-have):

- `site_unreachable`: boolean derived from `preflight_failed` payload flag

---

## Re-audit and repair

### Operator re-run

`ProspectAuditService::queueSiteAudit()` / `auditResetFields()` already clears `raw_a11y_payload`, sets `audit_status: pending`, and dispatches `AuditSiteJob`. Next run hits preflight again — fixed URL proceeds to full audit.

### `scanner:repair-audits`

Unreachable prospects match **failed site audits** category (`FailedSiteAuditQuery`). Repair resets audit fields and re-dispatches `AuditSiteJob` — preflight runs again. Reason string includes `audit_jobs.error_message` (e.g. `audit_status failed: DNS resolution failed`).

No changes to repair command surface; ensure preflight failures create accessibility `audit_jobs` with `failed` status so reason text is useful.

---

## Defensive: screenshot path

Even with preflight, align `BrowserScreenshotGateway` with audit gateway behaviour:

- When HTTP 200 response includes `{ error: "..." }` payload (page load failure from `screenshot.js`), **do not throw** — return structured failure or skip capture gracefully
- `CaptureScreenshotJob`: if prospect has `preflight_failed` or `audit_status: failed`, return early without capture

Prevents recurrence of `failed_jobs` stack traces for unreachable URLs if screenshot is ever dispatched for legacy rows.

---

## Testing

| Area | Tests |
|------|-------|
| `WebsiteReachabilityService` | Permanent DNS fail (no retry); transient timeout (retries then fail); 200 success |
| `SiteScanPreflightGate` | Unreachable → prospect failed fields, `CombineScoresJob` dispatched, no `AuditRunnerService` call |
| `AuditSiteJob` | Integration: mock unreachable → job completes without throw; reachable → existing audit path |
| `CombineScoresJob` | Preflight failed prospect keeps `audit_status: failed` after combine |
| `CaptureScreenshotJob` | Skips when `audit_status: failed` or `preflight_failed` |
| `BrowserScreenshotGateway` | Surfaces page error payload without throw (optional hardening) |
| `FailedSiteAuditQuery` | Preflight-failed prospects included |
| `SearchProspectResource` | `audit_error` populated for preflight failure |

---

## Out of scope

- `SiteScanOrchestratorJob` implementation
- Structured data / JSON-LD scan job, scoring, UI
- Auto-generating reports for unreachable sites
- Bulk backfill of legacy soft-fail rows to `failed`
- Fly `/reachability` browser endpoint
- Submission-time preflight in `SearchController` (queue-time gate only for this phase)

---

## Migration notes

- No database migration required — reuse `raw_a11y_payload` JSON with `preflight_failed: true`
- Deploy order: app code first; config defaults enable preflight immediately
- Monitor: count of `audit_status: failed` with `preflight_failed`; Fly audit volume drop for dead domains

---

## Related specs

- `2026-06-02-audit-repair-design.md` — failed audit retry
- `2026-06-02-audit-job-error-details-design.md` — error storage
- `2026-06-01-cms-detection-design.md` — future parallel scan module pattern
- `2026-06-05-lean-audit-refactor-design.md` — audit pipeline boundaries
