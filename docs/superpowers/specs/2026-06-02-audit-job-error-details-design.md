# Audit job error details — design

**Date:** 2026-06-02  
**Status:** Approved for implementation planning  
**Problem:** `audit_jobs.error_message` stores only a thin slice of Playwright/script failures (effectively the first line). Operators lack call log, stderr, and HTTP context needed to diagnose hard audit failures.

---

## Goals

1. Persist a **255-character summary** on `audit_jobs` for list views, MCP, and lightweight consumers.
2. Persist the **full diagnostic** in a separate table that can be **purged by age** without losing the summary on the job row.
3. Enrich error capture at the **Node** and **PHP** boundaries so multi-line Playwright output reaches storage.
4. Show the **full error on `/prospects/{id}`** in the operator GUI only — not via MCP or other API surfaces.

## Non-goals

- Soft page-load failures (`raw_a11y_payload['error']` with `audit_jobs.status = complete`) — follow-up if needed.
- Changing MCP scan monitoring payloads (`audit_error` stays summary-only).
- Exposing full diagnostics on `GET /api/mcp` or new JSON API routes.
- Search results row showing full text (summary only, unchanged).

---

## Data model

### `audit_jobs.error_message` (existing column)

- **Role:** Operator summary (single line).
- **Max length:** 255 characters on write.
- **Generation:** First non-empty line of the full diagnostic, trimmed; fallback `"Audit failed"` if empty.
- **Retention:** Not purged by the error-detail retention job (stays on the job row for history).

### New table: `audit_job_error_details`

| Column | Type | Notes |
|--------|------|--------|
| `id` | bigint PK | |
| `audit_job_id` | FK → `audit_jobs`, **unique**, `cascadeOnDelete` | One row per failed job |
| `body` | `text` | Full diagnostic; capped at **32 KB** on write |
| `created_at` | timestamp | Used for time-based purge |

No `updated_at`.

**Eloquent**

- `AuditJob` → `hasOne(AuditJobErrorDetail::class, 'errorDetail')`
- `AuditJobErrorDetail` → `belongsTo(AuditJob::class)`

**Indexes**

- Unique on `audit_job_id`
- Index on `created_at` (purge scans)

---

## Capture pipeline

### Node (`scripts/audit.js`, `scripts/browser-service/server.mjs`)

Introduce shared helper (e.g. `scripts/audit-error-format.js`):

- Inputs: `error`, optional `stderr`, `stdout`, optional `stage` (`page.goto`, `axe`, `lighthouse`, etc.).
- Output: Single multi-line string: message, Playwright call log (when present in message or stderr), trimmed stderr/stdout (avoid duplicating identical content).
- **audit.js** catch: set payload `error` to formatted string (not `error.message` alone).
- **runNodeScript** reject: use combined stderr + stdout when stdout is not valid JSON.
- **HTTP 500** handler: return `{ error: formattedOrMessage }` with full text where available (not `error.message` only on the wrapper).

Deploy **browser-service** on Fly when using `AUDIT_DRIVER=http`.

### PHP `AuditErrorRecorder` (new service)

```text
recordFailure(AuditJob $job, string $fullBody): void
```

1. Normalize `$fullBody` (trim; cap at 32 KB; log overflow with `audit_job_id`, `prospect_id`).
2. Derive summary (first line, max 255).
3. Update `audit_jobs.error_message` + `status = failed` (caller may already set status — recorder owns message fields only).
4. `AuditJobErrorDetail::create([...])`.

**Call sites:** `AuditSiteJob`, `CaptureScreenshotJob` (both create `audit_jobs` on failure today).

**Enrichment before record:**

| Source | Change |
|--------|--------|
| `AuditRunnerService` | On Process failure, combine **both** `errorOutput()` and `output()` in exception message |
| `BrowserServiceClient` | On throw paths, include HTTP body / nested JSON `error` in string passed to recorder |

---

## Read paths

| Consumer | Data |
|----------|------|
| Search list (`audit_error`) | `audit_jobs.error_message` summary only |
| MCP (`McpSearchService`) | Summary only — **no change** to include full body |
| `/prospects/{id}` (Inertia) | New prop `auditFailure` (see below) |
| `scanner:purge-expired` extension | Deletes aged rows from `audit_job_error_details` only |

### Inertia — `ProspectController::show` only

**Not** added to MCP, exports, or `routes/api.php`.

Eager-load `auditJobs.errorDetail` (or load latest failed accessibility job + detail).

New top-level prop `auditFailure`:

```php
// null when audit_status !== 'failed'
[
    'summary' => string,       // audit_jobs.error_message
    'full' => ?string,          // errorDetail.body; null if purged
    'detail_expired' => bool,  // true when failed but detail row missing
    'job_id' => int,
    'failed_at' => string ISO8601,
]
```

Resolve job: latest `audit_jobs` where `job_type = 'accessibility'` and `status = 'failed'` (fallback: latest failed job of any type).

### Operator UI — `Prospect/Show.jsx`

When `auditFailure` is present:

- Show a **Card** (e.g. title “Audit failed”) near score cards or above site audit section.
- **Summary** as visible one-line text (matches search list).
- **Full diagnostic** in a monospace `<pre>` inside a collapsible **“View full diagnostic”** disclosure (default collapsed) — keeps large logs off the initial viewport.
- If `detail_expired`: show summary + note: “Full diagnostic expired (retention).”

Reuse existing severity/colour tokens (`--color-sev-critical`).

---

## Retention

- Config: `audit_error_detail_retention_days` in `config/scanner.php`, env `AUDIT_ERROR_DETAIL_RETENTION_DAYS`, default **90**.
- Extend **`scanner:purge-expired`** (daily schedule already exists):
  - Delete `audit_job_error_details` where `created_at < now()->subDays(retention)`.
  - Do **not** clear `audit_jobs.error_message`.
- Prospect delete still cascades job + detail via FK.

Document env in `.env.example`.

---

## Testing

| Layer | Cases |
|-------|--------|
| Unit | Summary extraction: multi-line → first line; 255 cap; empty → fallback |
| Unit | Recorder: creates detail row; summary on job; 32 KB cap |
| Unit | `AuditRunnerService` / `BrowserServiceClient` enrichment (mocks) |
| Feature | Failed `AuditSiteJob` leaves summary + detail |
| Feature | `ProspectShowTest`: failed prospect includes `auditFailure.full`; purged detail sets `detail_expired` |
| Feature | Purge command removes old details, keeps summaries |
| Node (optional) | `formatAuditError` includes stderr when message is single-line |

---

## File touch list (implementation reference)

| Area | Files |
|------|--------|
| Migration | `create_audit_job_error_details_table` |
| Models | `AuditJobErrorDetail`, `AuditJob` relation |
| Services | `AuditErrorRecorder`, `AuditRunnerService`, `BrowserServiceClient` |
| Jobs | `AuditSiteJob`, `CaptureScreenshotJob` |
| Node | `audit-error-format.js`, `audit.js`, `browser-service/server.mjs` |
| Purge | `PurgeExpiredProspectData` |
| Config | `config/scanner.php`, `.env.example` |
| GUI | `ProspectController`, `Prospect/Show.jsx` (+ small component optional) |
| Tests | As above |

**Explicitly unchanged:** `McpSearchService`, `SearchController` audit_error shape, `routes/api.php`.

---

## CaptureScreenshotJob — retry, overlap, and idempotency (GAP-15)

`CaptureScreenshotJob` shares the `AuditErrorRecorder` failure path with `AuditSiteJob` but has distinct queue behaviour operators should understand when diagnosing stuck screenshots.

| Concern | Behaviour |
|---------|-----------|
| Retries | `tries = 3`, `backoff = [60, 120]`. On failure the job records summary + detail via `AuditErrorRecorder`, marks the attempt’s `audit_jobs` row `failed`, then **rethrows** so Laravel schedules the next attempt. |
| Overlap | `WithoutOverlapping('fly-browser-screenshot')` with `releaseAfter(180)` and `expireAfter(600)`. Only one screenshot job holds the lock at a time; others wait or retry later. |
| Idempotency | Skips when `screenshot_paths.desktop` is already populated or the prospect has no `website_url`. Safe to re-dispatch from `scanner:repair-audits` when desktop is still missing. |
| Per-attempt audit row | Each run creates a new `audit_jobs` row (`job_type = screenshot`). Failed attempts remain in history; the latest successful run sets `screenshot_paths` and completes its row. |

**Tests:** Feature coverage for rethrow + retry is optional; unit/feature tests should assert `AuditErrorRecorder` is invoked on failure and that an existing `desktop` path prevents re-capture.

---

## Decisions log

| Decision | Choice |
|----------|--------|
| Summary + full storage | Separate table for full body; summary on `audit_jobs` |
| List / MCP display | Summary only |
| Full error in GUI | Inertia prop on prospect show only; collapsible `<pre>` |
| Retention default | 90 days for detail rows |
| Purge mechanism | Extend `scanner:purge-expired` |
| Node + PHP capture | Both (recommended hybrid) |
