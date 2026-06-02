# Audit repair — Design Spec

**Date:** 2026-06-02  
**Status:** Approved for implementation planning  
**Scope:** Artisan command to kickstart stuck site audits, retry all failed site audits, and retry failed/stuck screenshot captures — without re-running Google Places / GBP scoring.

**Approach:** New `scanner:repair-audits` command with query support classes (mirrors `IncompleteAuditQuery` / `scanner:backfill-audits`). Keeps `scanner:backfill-audits` unchanged for payload-incomplete backfill only.

---

## Goal

Operators have a large backlog of failed `audit_jobs` and prospects stuck `pending` with no auditing-queue worker processing them (worker crash, lost dispatch, managed-queue visibility timeout). Searches remain in **Auditing** indefinitely. Existing `scanner:backfill-audits` only targets finished prospects with incomplete payloads and explicitly excludes stuck `pending` rows.

This command provides one safe, repeatable repair path for:

1. **Stuck site audits** — `audit_status = pending` (or stale running accessibility `audit_jobs`) with no matching queue job
2. **Failed site audits** — all `audit_status = failed` prospects (fresh audit run, regardless of payload completeness)
3. **Failed / stuck screenshots** — `audit_jobs.job_type = screenshot` in `failed` or stale `running` state

**GBP guarantee:** Never dispatches `ScorePlaceJob`, `ScrapeProspectsJob`, or any path that re-fetches Google Business Profile / Places data. Site-audit repair resets audit fields and dispatches `AuditSiteJob` only.

---

## Decisions

| Topic | Decision |
|-------|----------|
| Failed retries | Site audits and screenshots handled separately |
| Stuck in-flight | Included — pending prospects and stale running `audit_jobs` |
| Stuck detection | Age threshold **and** no matching auditing-queue job (age-only when connection is `cloud`) |
| Failed site audits | Retry **all** `audit_status = failed` with website (not payload-gated) |
| GBP / Places API | Never re-dispatch scoring/scrape jobs |
| Command | New `scanner:repair-audits`; `scanner:backfill-audits` unchanged |
| Default scope | All three categories in one dry-run / execute pass |
| Downstream pipeline | Site: `AuditSiteJob` → `CombineScoresJob` → (report/screenshot per existing suppress rules). Screenshot: `CaptureScreenshotJob` only |
| Safety default | Dry-run unless `--execute` |
| Stale default | `--stuck-after=15` minutes |

---

## Command surface

```bash
php artisan scanner:repair-audits [--execute] [--search=] [--prospect=] [--limit=] [--delay=5] [--stuck-after=15] [--only=stuck|failed|screenshots] [--skip-screenshots]
```

| Flag | Default | Purpose |
|------|---------|---------|
| *(none)* | dry-run | Preview matches; no writes or dispatches |
| `--execute` | off | Close stale jobs, reset prospects, dispatch queue jobs |
| `--search=` | none | Limit to one search |
| `--prospect=` | none | Limit to one prospect (site-audit categories) |
| `--limit=` | none | Cap dispatches per category per run |
| `--delay=` | `5` | Seconds between each dispatch (SQS-aware via `QueueDispatchDelay`) |
| `--stuck-after=` | `15` | Minutes before pending/running counts as stale |
| `--only=` | all categories | Restrict to one category |
| `--skip-screenshots` | off | Site audits only |

### Dry-run output

1. Summary line: `stuck: N, failed: M, screenshots: K`
2. Table columns: `category`, `prospect_id`, `report_id`, `search_id`, `reason`
3. When auditing connection is `cloud`: note `queue check skipped (cloud connection)` in output for stuck rows
4. Exit `0`; message `Nothing to repair` when all counts are zero

---

## Selection rules

Three independent queries. A prospect appears in at most one **site-audit** category (stuck takes precedence over failed).

### Shared scope (all categories)

- Related search `status` ∈ `auditing`, `complete`
- `website_url` not null and not empty
- Stable order by `prospect.id` (screenshots by `prospect_report.id`)
- Optional filters: `--search=`, `--prospect=`, `--limit=`

### Category 1: Stuck site audits (`StuckSiteAuditQuery`)

All must hold:

1. Search `scan_type` ∈ `accessibility_only`, `combined`
2. `audit_status = pending`
3. **Stale:** `prospects.updated_at < now()->subMinutes(stuck-after)`, OR latest accessibility `audit_jobs` row has `status = running` and `started_at` older than threshold
4. **No queue job:** `AuditingQueuePresence::hasPendingAuditSiteJob($prospectId)` is false (see Queue presence)
5. Exclude when `config('scanner.audit_driver') === 'skip'` and prospect would not receive audits

**Reason examples:** `pending without queue job (stale 18m)`, `running audit_job #456 stale without queue job`

### Category 2: Failed site audits (`FailedSiteAuditQuery`)

All must hold:

1. Search `scan_type` ∈ `accessibility_only`, `combined`
2. `audit_status = failed`
3. Not already selected as stuck (dedupe)
4. Not `audit_status = skipped` when `audit_driver = skip`

Includes failures with complete payloads — operator explicitly wants a fresh audit run.

**Reason examples:** `audit_status failed`, append latest accessibility `audit_jobs.error_message` when present

### Category 3: Failed / stuck screenshots (`FailedScreenshotQuery`)

Each `ProspectReport` matches when all hold:

1. Related prospect has non-empty `website_url`
2. Latest `audit_jobs` row where `job_type = screenshot` has:
   - `status = failed`, **or**
   - `status = running` **and** stale per `--stuck-after` **and** `AuditingQueuePresence::hasPendingScreenshotJob($reportId)` is false
3. Report row exists (`prospect_reports.prospect_id`)

Independent of `prospects.audit_status` — a `complete` prospect may still need screenshot repair.

**Reason examples:** `screenshot failed`, `screenshot running stale without queue job`

---

## Queue presence check

`AuditingQueuePresence` helper:

- `hasPendingAuditSiteJob(int $prospectId): bool`
- `hasPendingScreenshotJob(int $reportId): bool`

| `AuditingQueue::connection()` | Behaviour |
|-------------------------------|-----------|
| `database` | Query `jobs` where `queue = auditing`; match serialized payload containing the prospect/report identifier for `AuditSiteJob` / `CaptureScreenshotJob` |
| `cloud` | Always return `false` for presence (jobs not in Postgres); **stuck = age threshold only**. Dry-run notes this for operator awareness |

Production hybrid deployments use `AUDITING_QUEUE_CONNECTION=cloud`; local / all-database setups get the full age + queue check.

---

## Execute behaviour

### Site audits (stuck + failed)

For each selected prospect:

1. Close stale accessibility `audit_jobs` where `status = running`:
   - `status → failed`
   - `error_message → 'Closed by scanner:repair-audits (stale)'` (255 cap)
   - `completed_at → now()`
2. Call `ProspectAuditService::queueSiteAudit($prospect, suppressAutoReport: true, delaySeconds: QueueDispatchDelay::forIndex(...))` — same audit reset fields as `scanner:backfill-audits`
3. Do **not** modify GBP fields (`gbp_score`, `gbp_flags`, `raw_gbp_payload`, `combined_score`, etc.)

Existing job chain: `AuditSiteJob` → `CombineScoresJob` → `GenerateProspectReportJob` / `CaptureScreenshotJob` per current suppress and website rules.

### Screenshots (failed + stuck)

For each selected report:

1. Close stale running screenshot `audit_jobs` (same stale-close pattern as site audits)
2. `CaptureScreenshotJob::dispatch($report)` with staggered delay via `AuditingQueue`
3. Do **not** reset prospect audit fields or dispatch `AuditSiteJob`

### Throttling

- Reuse `QueueDispatchDelay::maxJobsPerBatch()` and SQS cap warning (same as backfill)
- Dedupe within run: each prospect dispatched at most once for site audit; each report at most once for screenshot
- Summary: `Dispatched N site audit(s), M screenshot(s)` with per-category counts

---

## Edge cases

| Case | Behaviour |
|------|-----------|
| Prospect in both stuck and failed | Stuck wins; dedupe before failed query |
| `audit_driver = skip` | Exclude from stuck/failed site-audit selection |
| Search `status` not in scope | Excluded |
| `gbp_only` scan | Excluded from site-audit categories |
| Failed site audit with complete payloads | Included (unlike backfill) |
| Screenshot failed but prospect `complete` | Included in screenshot category |
| `--limit` smaller than match count | Dispatch first N per category (stable order); warn remaining |
| Cloud connection stuck detection | Age-only; document in dry-run output |
| `GenerateProspectReportJob` after re-audit | May call Places if `benchmark_snapshot` missing — rare for normal search prospects; out of scope for v1 (`--no-report` flag deferred) |
| Repair after execute still fails | `audit_status → failed`; operator fixes infra and re-runs |
| Expired prospect | Still eligible; purge command handles expiry separately |

---

## Relationship to `scanner:backfill-audits`

| Command | Purpose |
|---------|---------|
| `scanner:backfill-audits` | Finished prospects (`complete`/`failed`) with **incomplete payloads** (missing Lighthouse, etc.) |
| `scanner:repair-audits` | Stuck `pending`, all hard **failed** site audits, failed/stuck **screenshots** |

Operators may run both: repair unblocks pipeline; backfill fills historical payload gaps. Overlap is minimal (backfill excludes `pending`; repair includes all `failed` even with complete payloads).

---

## Implementation

### New files

| File | Role |
|------|------|
| `app/Console/Commands/RepairAuditsCommand.php` | CLI, dry-run table, execute path, category orchestration |
| `app/Support/StuckSiteAuditQuery.php` | Stuck site audit selection + reason |
| `app/Support/FailedSiteAuditQuery.php` | Failed site audit selection + reason |
| `app/Support/FailedScreenshotQuery.php` | Failed/stuck screenshot selection + reason |
| `app/Support/AuditingQueuePresence.php` | Queue job presence checks (connection-aware) |

Laravel auto-discovers the command via `app/Console/Commands/`.

### Reused (no changes required)

- `ProspectAuditService::queueSiteAudit()` — reset + dispatch
- `QueueDispatchDelay` — stagger + SQS cap
- `AuditingQueue` — connection/queue routing
- `AuditSiteJob`, `CaptureScreenshotJob`, downstream chain

### Documentation

- Add **Audit repair** subsection to `docs/deployment/laravel-cloud.md` near **Failed audits**
- Cross-link from existing backfill section

---

## Operations (Laravel Cloud)

Run on an **app** instance (scheduler/web):

```bash
# Preview all categories
php artisan scanner:repair-audits

# Execute (staggered)
php artisan scanner:repair-audits --execute --delay=5

# One search, capped batch
php artisan scanner:repair-audits --execute --search=42 --limit=50 --delay=5

# Stuck only, tighter stale window
php artisan scanner:repair-audits --execute --only=stuck --stuck-after=10
```

Prerequisites:

- Auditing queue workers / managed `auditing` queue healthy
- `AUDIT_SERVICE_URL` set so audits route to Fly HTTP in production

---

## Testing

### Query unit tests

| Fixture | Expect |
|---------|--------|
| `pending`, stale, no queue job, combined scan | Stuck match |
| `pending`, fresh, no queue job | No stuck match |
| `pending`, stale, queue job present (database connection) | No stuck match |
| `failed`, complete payloads | Failed match |
| `failed`, excluded by stuck dedupe | Failed skip |
| `complete` prospect, failed screenshot job | Screenshot match |
| Search `status = pending` | Excluded |
| `gbp_only` scan | Excluded from site categories |
| `audit_driver = skip` | Excluded from site categories |

### `AuditingQueuePresence` unit tests

- Database connection: detects serialized `AuditSiteJob` prospect id in payload
- Cloud connection: returns false (documented behaviour)

### `RepairAuditsCommand` feature tests

- Dry-run: no DB changes, `Queue::fake()` → no jobs pushed; table includes category column
- `--execute` stuck: running `audit_job` closed, prospect reset to `pending`, `AuditSiteJob` pushed
- `--execute` failed: same reset path for failed prospect
- `--execute` screenshot: `CaptureScreenshotJob` pushed; prospect audit fields unchanged
- `--only=failed`: no screenshot dispatches
- Dedupe: prospect not dispatched twice when would match multiple rules

---

## Out of scope

- Automatic scheduled repair or retry loops
- Re-dispatching `ScorePlaceJob` / GBP re-scrape
- Combine-only or report-only repair without site audit
- Inspecting managed SQS queue contents directly
- `--no-report` flag to skip `GenerateProspectReportJob` (deferred)
- Soft page-load failures (`raw_a11y_payload['error']` with `audit_status = complete`) — use backfill or manual re-audit
- Changing or deleting historical `audit_jobs` rows beyond closing stale `running` rows

---

## Decisions log

| Decision | Choice |
|----------|--------|
| Command name | `scanner:repair-audits` |
| vs backfill | Separate command; backfill unchanged |
| Failed site audit scope | All `failed`, not payload-gated |
| Stuck detection | Age + queue check; age-only on `cloud` |
| Default run | All categories |
| Screenshot repair | Direct `CaptureScreenshotJob` dispatch |
| GBP | Never re-scrape; site repair via `queueSiteAudit` only |
| Stale `running` jobs | Close as failed before re-dispatch |
