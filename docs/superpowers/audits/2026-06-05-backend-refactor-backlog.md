# Backend Refactor Backlog

**Date:** 2026-06-05
**Baseline:** Laravel 13.8
**Spec:** [2026-06-05-lean-audit-refactor-design.md](../specs/2026-06-05-lean-audit-refactor-design.md)

## Executive summary
_TBD after Task 14_

## Keep (positive patterns)
_TBD after Task 14_

## Spec gap register
_TBD after Task 12_

## Findings by subsystem
### S1 — Search & prospecting

#### REF-S1-01: DirectUrlScanJob test drift — missing sixth DI argument

| Field | Value |
|-------|-------|
| Subsystem | S1 Search & prospecting |
| Scores | M=3 R=1 L=2 O=3 → Total 9 (P2) |
| Evidence | `tests/Feature/DirectUrlScanJobTest.php:44,78,158`; `app/Jobs/DirectUrlScanJob.php:39` |
| Spec gap | [2026-05-28-single-site-url-audit-design.md](../specs/2026-05-28-single-site-url-audit-design.md) — `ProspectExclusionService` added to job handle signature; tests not updated |
| PR slice | Medium — "S1: fix DirectUrlScanJobTest handle() DI (ProspectExclusionService)" |
| Risk | Low — test-only; production job resolves via container |
| Effort | ~0.5 hours |
| Notes | `php artisan test --filter=DirectUrlScanJobTest` → 3 errors, 1 pass. Manual `handle()` calls pass 5 deps; `handle()` expects 6. `dispatchSync` path (line 113) passes. Appendix baseline confirms same failures. |

#### REF-S1-02: DirectUrlScanJob lacks idempotency guard on queue retry

| Field | Value |
|-------|-------|
| Subsystem | S1 Search & prospecting |
| Scores | M=2 R=2 L=2 O=3 → Total 9 (P2) |
| Evidence | `app/Jobs/DirectUrlScanJob.php:77,88`; contrast `app/Jobs/ScorePlaceJob.php:39-45,66-80` |
| Spec gap | [2026-05-28-single-site-url-audit-design.md](../specs/2026-05-28-single-site-url-audit-design.md) — no retry/idempotency semantics documented |
| PR slice | Medium — "S1: add DirectUrlScanJob idempotency guard (match ScorePlaceJob pattern)" |
| Risk | Medium — retry hits `unique(search_id, place_id)` and marks search `failed` |
| Effort | ~1–2 hours |
| Notes | Uses `Prospect::create` with no pre-check. Sibling `ScorePlaceJob` early-returns when prospect exists and audit is not pending. |

#### REF-S1-03: WebsiteDiscoveryService hardcodes `google_cse` source when Brave is default provider

| Field | Value |
|-------|-------|
| Subsystem | S1 Search & prospecting |
| Scores | M=2 R=1 L=2 O=2 → Total 7 (P2) |
| Evidence | `app/Services/WebsiteDiscoveryService.php:157`; `config/scanner.php` `website_discovery_provider` defaults to `brave` |
| Spec gap | [2026-06-04-website-discovery-design.md](../specs/2026-06-04-website-discovery-design.md) — enum documents `gbp`/`google_cse`/`operator` only; spec predates Brave as default |
| PR slice | Medium — "S1: align website_url_source with active discovery provider" |
| Risk | Medium — operator UI shows wrong provenance; may need enum/migration extension |
| Effort | ~2 hours |
| Notes | `applyMatch()` always sets `website_url_source = 'google_cse'` regardless of `scanner.website_discovery_provider`. Tests assert `google_cse` even for Brave mocks. |

#### REF-S1-04: WebsiteDiscoveryService decomposition candidate (316 lines)

| Field | Value |
|-------|-------|
| Subsystem | S1 Search & prospecting |
| Scores | M=2 R=1 L=2 O=1 → Total 6 (P3) |
| Evidence | `app/Services/WebsiteDiscoveryService.php` (316 lines); appendix file-size signal #3 |
| Spec gap | None |
| PR slice | Medium — "S1: extract WebsiteDiscoveryMatcher from WebsiteDiscoveryService" |
| Risk | Low — covered by `WebsiteDiscoveryServiceTest` and `ScorePlaceJobWebsiteDiscoveryTest` |
| Effort | ~3–4 hours |
| Notes | Cohesive but dense: provider routing, two-tier `matchCandidates`, `applyMatch` rescoring, logging. Natural split: matcher (tokens/tiers) vs applicator (persist + rescore). |

#### REF-S1-05: SearchController `store()` uses inline validation; `storeDirectUrl` uses Form Request

| Field | Value |
|-------|-------|
| Subsystem | S1 Search & prospecting |
| Scores | M=2 R=1 L=2 O=1 → Total 6 (P3) |
| Evidence | `app/Http/Controllers/SearchController.php:76-81`; `app/Http/Requests/StoreDirectUrlSearchRequest.php` |
| Spec gap | None — appendix Form Request gap already flags `SearchController.php:76` |
| PR slice | Medium — "S1: add StoreSearchRequest for niche area-scan form" |
| Risk | Low |
| Effort | ~1 hour |
| Notes | Asymmetric validation: direct-URL path is idiomatic; niche `store()` inline. Rate-limit logic also duplicated across both methods (lines 65–74 vs 95–104). |

#### REF-S1-06: SearchController `show()` fat prospect serialization in controller

| Field | Value |
|-------|-------|
| Subsystem | S1 Search & prospecting |
| Scores | M=2 R=1 L=2 O=1 → Total 6 (P3) |
| Evidence | `app/Http/Controllers/SearchController.php:124-201` (220-line controller) |
| Spec gap | None |
| PR slice | Medium — "S1: extract Search show prospect payload builder" |
| Risk | Low — F1 may overlap on Inertia prop bloat |
| Effort | ~2–3 hours |
| Notes | ~60 lines of prospect mapping (scores, audit, report, CMS, progress flow) inline in `show()`. Eager-loading is correct (`with` on lines 133–137); boundary is the issue. |

#### REF-S1-07: ScrapeProspectsJob injects unused SearchStatusService

| Field | Value |
|-------|-------|
| Subsystem | S1 Search & prospecting |
| Scores | M=2 R=1 L=2 O=1 → Total 6 (P3) |
| Evidence | `app/Jobs/ScrapeProspectsJob.php:32-33` — `$searchStatus` never referenced in `handle()` body |
| Spec gap | None |
| PR slice | Medium — "S1: remove dead SearchStatusService injection from ScrapeProspectsJob" |
| Risk | Low — update `ScrapeProspectsJobTest` mock list |
| Effort | ~0.5 hours |

#### REF-S1-08: GooglePlacesService blocking `sleep(2)` in queue worker pagination loop

| Field | Value |
|-------|-------|
| Subsystem | S1 Search & prospecting |
| Scores | M=1 R=2 L=2 O=2 → Total 7 (P2) |
| Evidence | `app/Services/GooglePlacesService.php:72-73` |
| Spec gap | None |
| PR slice | Medium — "S1: remove blocking sleep from Places pagination (config/async chunk)" |
| Risk | Low — Places API requires delay; worker occupancy is the cost |
| Effort | ~2 hours |
| Notes | Up to 4s blocked per `searchByNicheAndCity` call on `searches` queue worker. Called from `ScrapeProspectsJob`. |

#### REF-S1-09: SearchStatusService runs three separate prospect count queries per refresh

| Field | Value |
|-------|-------|
| Subsystem | S1 Search & prospecting |
| Scores | M=1 R=2 L=2 O=1 → Total 6 (P3) |
| Evidence | `app/Services/SearchStatusService.php:27-47` |
| Spec gap | None |
| PR slice | Medium — "S1: consolidate SearchStatusService refresh into single aggregate query" |
| Risk | Low |
| Effort | ~1 hour |
| Notes | Hot path after every `ScorePlaceJob`, `DirectUrlScanJob`, `AuditSiteJob`, `CombineScoresJob` completion. |

#### REF-S1-10: BackfillWebsitesCommand loads full candidate set into memory for count/filter

| Field | Value |
|-------|-------|
| Subsystem | S1 Search & prospecting |
| Scores | M=2 R=2 L=2 O=1 → Total 7 (P2) |
| Evidence | `app/Console/Commands/BackfillWebsitesCommand.php:164-182` (`get()->filter()` for count and fetch) |
| Spec gap | None — dry-run pattern matches spec (`--execute` gate on line 73) |
| PR slice | Medium — "S1: push backfill eligibility into SQL for BackfillWebsitesCommand" |
| Risk | Low — command is operator-batch, not hot path |
| Effort | ~2–3 hours |
| Notes | Dry-run support is good. `countCandidates()` materialises all matching prospects then filters in PHP via `isBackfillCandidate()`. `fetchCandidates()` over-fetches `limit * 3` for same reason. |

#### REF-S1-11: `mapSearchSummary` duplicated between SearchController and McpSearchService

| Field | Value |
|-------|-------|
| Subsystem | S1 Search & prospecting |
| Scores | M=2 R=1 L=2 O=1 → Total 6 (P3) |
| Evidence | `app/Http/Controllers/SearchController.php:207-218`; `app/Services/Mcp/McpSearchService.php:143-154` |
| Spec gap | None |
| PR slice | Medium — "S1: share SearchSummaryMapper between web and MCP surfaces" |
| Risk | Low — intentional `created_at` format difference (human vs ISO) needs preserving |
| Effort | ~1–2 hours |

### S2 — Niche scanning

#### REF-S2-01: ScanNichesCommandTest hardcoded scan_date drift

| Field | Value |
|-------|-------|
| Subsystem | S2 Niche scanning |
| Scores | M=2 R=1 L=3 O=3 → Total 9 (P2) |
| Evidence | `tests/Feature/ScanNichesCommandTest.php:66-81`; `app/Console/Commands/ScanNichesCommand.php:54,95-102` |
| Spec gap | None — skip logic matches [2026-05-27-niche-opportunity-scanner-design.md](../specs/2026-05-27-niche-opportunity-scanner-design.md) |
| PR slice | Medium — "S2: fix ScanNichesCommandTest scan_date to match travelTo" |
| Risk | Low — test-only; production skip-by-scan_date works |
| Effort | ~0.5 hours |

Test seeds `scan_date => '2026-05-29'` but `travelTo(now('Europe/London')->startOfDay())` yields 2026-06-05, so `alreadyComplete()` misses the row and command outputs `Dispatched 1` instead of `Skipped 1`. `php artisan test --filter=ScanNichesCommandTest` → 1 failure (3 pass).

#### REF-S2-02: NicheScanSampleController re-dispatches job on every poll

| Field | Value |
|-------|-------|
| Subsystem | S2 Niche scanning |
| Scores | M=2 R=3 L=2 O=3 → Total 10 (P1) |
| Evidence | `app/Http/Controllers/NicheScanSampleController.php:36-47`; `resources/js/Components/Niches/NicheSamplePanel.jsx:17-62` |
| Spec gap | [2026-05-28-niches-pagination-sample-panel-design.md](../specs/2026-05-28-niches-pagination-sample-panel-design.md) — spec says skip duplicate dispatch when `pending`; code only guards `pending`, not in-flight backfill |
| PR slice | Medium — "S2: dispatch sample backfill once per row (pending/in-flight guard)" |
| Risk | High — up to 30 duplicate Places jobs per panel open (2s × 30 polls); API quota burn |
| Effort | ~1–2 hours |

When `sample_preview` is null and `status === 'complete'`, every poll re-dispatches `ScanNicheJob`. Spec concurrency rule covers `pending` only. Panel polls every 2s (max 30).

#### REF-S2-03: Legacy sample backfill writes today's row, not polled row

| Field | Value |
|-------|-------|
| Subsystem | S2 Niche scanning |
| Scores | M=2 R=2 L=2 O=3 → Total 9 (P2) |
| Evidence | `app/Http/Controllers/NicheScanSampleController.php:37-44`; `app/Jobs/ScanNicheJob.php:54-67`; `docs/niches.md:309-315` |
| Spec gap | [2026-05-28-niches-pagination-sample-panel-design.md](../specs/2026-05-28-niches-pagination-sample-panel-design.md) — backfill intentionally uses today's `scan_date`; legacy rows never receive `sample_preview` on polled id |
| PR slice | Medium — "S2: backfill sample_preview on requested NicheScan row (or redirect poll to latest)" |
| Risk | Medium — pre-migration rows without `sample_preview` time out in panel; operator sees perpetual loading then failure |
| Effort | ~2–3 hours |

Backfill dispatches with `scanDate: now()` while client polls `/niches/{id}/sample` on the historical row. Job upserts `(niche, city, scan_date=today)` — original row stays null. Acknowledged in `docs/niches.md` as follow-up.

#### REF-S2-04: ScanNicheJob lacks idempotency guard on queue retry

| Field | Value |
|-------|-------|
| Subsystem | S2 Niche scanning |
| Scores | M=2 R=2 L=2 O=3 → Total 9 (P2) |
| Evidence | `app/Jobs/ScanNicheJob.php:38-68`; contrast `app/Jobs/ScorePlaceJob.php:39-45` |
| Spec gap | [2026-05-27-niche-opportunity-scanner-design.md](../specs/2026-05-27-niche-opportunity-scanner-design.md) — retry semantics not documented |
| PR slice | Medium — "S2: add ScanNicheJob idempotency guard (skip when complete unless forced)" |
| Risk | Medium — retry resets `status=pending` via `updateOrCreate` and re-calls Places API; may clobber aggregates mid-retry |
| Effort | ~1–2 hours |

`pendingScan()` always sets `status => 'pending'` with no early return when row is already `complete`. Sibling `ScorePlaceJob` returns when prospect exists and audit is not pending.

#### REF-S2-05: ScanNicheJob failure records status only — no operator-visible error

| Field | Value |
|-------|-------|
| Subsystem | S2 Niche scanning |
| Scores | M=1 R=1 L=2 O=2 → Total 6 (P3) |
| Evidence | `app/Jobs/ScanNicheJob.php:70-84`; `app/Models/NicheScan.php:9-24`; `database/migrations/2026_05_27_140000_create_niche_scans_table.php` |
| Spec gap | [2026-05-27-niche-opportunity-scanner-design.md](../specs/2026-05-27-niche-opportunity-scanner-design.md) — "log error" only; no column for message |
| PR slice | Medium — "S2: persist ScanNicheJob failure reason on niche_scans row" |
| Risk | Low — `failed()` logs to application log; sample panel shows generic "Sample scan failed." |
| Effort | ~2 hours |

`failed()` updates `status = 'failed'` and logs; no `error_message` column or JSON field. Sample endpoint returns generic 422 message.

#### REF-S2-06: Duplicated ROW_NUMBER latest-scan subquery

| Field | Value |
|-------|-------|
| Subsystem | S2 Niche scanning |
| Scores | M=2 R=1 L=2 O=1 → Total 6 (P3) |
| Evidence | `app/Http/Controllers/NicheScanController.php:20-28`; `app/Services/NicheExclusionService.php:144-154` |
| Spec gap | None |
| PR slice | Medium — "S2: extract LatestNicheScanQuery for index and exclusion max results" |
| Risk | Low — both queries tested independently (`NicheScanControllerTest`, `NicheExclusionServiceTest`) |
| Effort | ~1–2 hours |

Identical `ROW_NUMBER() OVER (PARTITION BY niche, city ORDER BY ran_at DESC, id DESC)` window in controller index and `maxLatestResultCount()`. Natural `app/Queries/LatestNicheScanQuery.php` extraction.

#### REF-S2-07: NichesBootstrapCommand decomposition candidate (342 lines)

| Field | Value |
|-------|-------|
| Subsystem | S2 Niche scanning |
| Scores | M=2 R=1 L=2 O=1 → Total 6 (P3) |
| Evidence | `app/Console/Commands/NichesBootstrapCommand.php` (342 lines); appendix file-size signal |
| Spec gap | [2026-05-27-niches-bootstrap-design.md](../specs/2026-05-27-niches-bootstrap-design.md) — spec explicitly chose single command class |
| PR slice | Medium — "S2: extract NichesBootstrapSteps from NichesBootstrapCommand" |
| Risk | Low — operator-run one-time command; covered by `NichesBootstrapCommandTest` |
| Effort | ~3–4 hours |

Cohesive pipeline (ONS fetch, taxonomy filter, Birmingham validation, PHP config writer) in one class. Spec decision is intentional; size still hurts navigation.

#### REF-S2-08: NicheIgnoreController uses inline validation

| Field | Value |
|-------|-------|
| Subsystem | S2 Niche scanning |
| Scores | M=2 R=1 L=2 O=1 → Total 6 (P3) |
| Evidence | `app/Http/Controllers/NicheIgnoreController.php:13-15,24-26` |
| Spec gap | None — appendix Form Request gap |
| PR slice | Medium — "S2: add StoreNicheIgnoreRequest for ignore/include routes" |
| Risk | Low |
| Effort | ~0.5 hours |

`store()` and `destroy()` both inline `$request->validate(['niche' => ...])`. Matches cross-cutting Form Request gap pattern (6 requests vs 18 store/update controllers).

### S3 — Audit pipeline

> **Keep candidates (not scored — for Task 14):** `RepairAuditsCommand` dry-run / `--execute` gate with category table and SQS batch warnings; `AuditingQueuePresence` connection-aware queue check (age-only when `cloud`); `ProspectAuditService::repairSiteAudit()` distinct from `queueSiteAudit()` (allows re-dispatch on stuck pending).

#### REF-S3-01: CaptureScreenshotJob swallows exceptions — Laravel retries never fire

| Field | Value |
|-------|-------|
| Subsystem | S3 Audit pipeline |
| Scores | M=2 R=1 L=2 O=3 → Total 8 (P2) |
| Evidence | `app/Jobs/CaptureScreenshotJob.php:24-25,81-95` — `tries = 3` but `catch` logs and returns without rethrow |
| Spec gap | [2026-06-02-audit-job-error-details-design.md](../specs/2026-06-02-audit-job-error-details-design.md) — error recording exists; retry semantics not documented for screenshot path |
| PR slice | Medium — "S3: rethrow CaptureScreenshotJob failures to honour tries/backoff" |
| Risk | Medium — transient Fly/browser errors recorded as terminal failed jobs with no automatic retry |
| Effort | ~1 hour |
| Notes | `AuditErrorRecorder` runs in catch; job exits successfully from queue worker perspective. Contrast `AuditSiteJob` rethrows after recording (line 118). |

#### REF-S3-02: CaptureScreenshotJob lacks idempotency guard on retry or repair redispatch

| Field | Value |
|-------|-------|
| Subsystem | S3 Audit pipeline |
| Scores | M=2 R=2 L=2 O=2 → Total 8 (P2) |
| Evidence | `app/Jobs/CaptureScreenshotJob.php:52-75`; contrast `app/Jobs/AuditSiteJob.php:47-49` |
| Spec gap | [2026-06-02-audit-repair-design.md](../specs/2026-06-02-audit-repair-design.md) — screenshot repair dispatches job; no skip-when-complete rule |
| PR slice | Medium — "S3: add CaptureScreenshotJob idempotency guard (skip when desktop path stored)" |
| Risk | Medium — repair or manual re-dispatch creates duplicate `audit_jobs` rows and redundant Fly captures |
| Effort | ~1–2 hours |
| Notes | Creates a new `AuditJob` (`screenshot`, `running`) on every handle. No check of `screenshot_paths['desktop']`. |

#### REF-S3-03: AuditSiteJob sets `audit_status = failed` then rethrows — queue retry is dead code

| Field | Value |
|-------|-------|
| Subsystem | S3 Audit pipeline |
| Scores | M=2 R=1 L=2 O=3 → Total 8 (P2) |
| Evidence | `app/Jobs/AuditSiteJob.php:25-26,47-49,107-118` |
| Spec gap | None — [2026-06-02-audit-job-error-details-design.md](../specs/2026-06-02-audit-job-error-details-design.md) covers recording; retry interaction undocumented |
| PR slice | Medium — "S3: align AuditSiteJob retry with audit_status (defer failed until tries exhausted)" |
| Risk | Medium — `tries = 2` never re-runs audit; operator must use `scanner:repair-audits` for transient failures |
| Effort | ~2 hours |
| Notes | Second attempt early-returns because `audit_status !== 'pending'`. Repair command is the intended recovery path; job-level retry config is misleading. |

#### REF-S3-04: No dedicated CaptureScreenshotJob test — behaviour covered only indirectly

| Field | Value |
|-------|-------|
| Subsystem | S3 Audit pipeline |
| Scores | M=2 R=1 L=2 O=2 → Total 7 (P2) |
| Evidence | `tests/Feature/RepairAuditsCommandTest.php:140`; no `CaptureScreenshotJobTest.php` in `tests/` |
| Spec gap | None |
| PR slice | Medium — "S3: add CaptureScreenshotJobTest (success, failure recording, idempotency)" |
| Risk | Low — repair command test asserts dispatch only; swallow-rethrow and storage paths untested |
| Effort | ~2 hours |

#### REF-S3-05: FailedScreenshotQuery N+1 — `latestScreenshotJob` per candidate in PHP filter

| Field | Value |
|-------|-------|
| Subsystem | S3 Audit pipeline |
| Scores | M=1 R=2 L=2 O=1 → Total 6 (P3) |
| Evidence | `app/Support/FailedScreenshotQuery.php:65-95,88-95` — `filterEligible()` calls `latestScreenshotJob()` per report |
| Spec gap | None |
| PR slice | Medium — "S3: batch latest screenshot audit_jobs in FailedScreenshotQuery" |
| Risk | Low — operator repair command, not request hot path |
| Effort | ~1–2 hours |
| Notes | `reasonFor()` repeats the same query. Repair dry-run table can materialise hundreds of rows. |

#### REF-S3-06: StuckSiteAuditQuery post-filter runs extra AuditJob query per prospect

| Field | Value |
|-------|-------|
| Subsystem | S3 Audit pipeline |
| Scores | M=1 R=2 L=2 O=1 → Total 6 (P3) |
| Evidence | `app/Support/StuckSiteAuditQuery.php:79-99,61-66` — `filterByQueuePresence()` and `reasonFor()` each query `audit_jobs` |
| Spec gap | None |
| PR slice | Medium — "S3: consolidate StuckSiteAuditQuery AuditJob lookups" |
| Risk | Low — repair command batch path |
| Effort | ~1–2 hours |

#### REF-S3-07: Dual CMS detection paths — audit payload vs standalone DetectCmsJob

| Field | Value |
|-------|-------|
| Subsystem | S3 Audit pipeline |
| Scores | M=2 R=1 L=2 O=2 → Total 7 (P2) |
| Evidence | `app/Jobs/AuditSiteJob.php:88-90`; `app/Jobs/ScorePlaceJob.php:114-116,121-123` |
| Spec gap | [2026-06-01-cms-detection-design.md](../specs/2026-06-01-cms-detection-design.md) — standalone job documented; inline audit payload path not in spec |
| PR slice | Medium — "S3/S4: single CMS detection path after site audit" |
| Risk | Medium — race or overwrite when audit embeds `cms` and `DetectCmsJob` runs in parallel (`audit_driver = skip` path) |
| Effort | ~2–3 hours |

#### REF-S3-08: Audit repair query objects live under `Support/` not `Queries/`

| Field | Value |
|-------|-------|
| Subsystem | S3 Audit pipeline |
| Scores | M=2 R=1 L=2 O=1 → Total 6 (P3) |
| Evidence | `app/Support/StuckSiteAuditQuery.php`, `FailedSiteAuditQuery.php`, `FailedScreenshotQuery.php`, `IncompleteAuditQuery.php`; contrast `app/Queries/ProspectListQuery.php` |
| Spec gap | None — appendix Support vs Queries overlap |
| PR slice | Medium — "S10/S3: move audit query objects to app/Queries/" |
| Risk | Low — navigation consistency; behaviour unchanged |
| Effort | ~1–2 hours |

#### REF-S3-09: CaptureScreenshotJob WithoutOverlapping uses global key for all reports

| Field | Value |
|-------|-------|
| Subsystem | S3 Audit pipeline |
| Scores | M=1 R=2 L=1 O=2 → Total 6 (P3) |
| Evidence | `app/Jobs/CaptureScreenshotJob.php:38-44` — key `'fly-browser-screenshot'` not scoped to report/prospect |
| Spec gap | None |
| PR slice | Medium — "S3: scope CaptureScreenshotJob WithoutOverlapping per report or document global serialization" |
| Risk | Low — may be intentional Fly single-browser constraint; repair batch serialises all captures |
| Effort | ~1 hour |

### S4 — Scoring & enrichment

#### REF-S4-01: ScorePlaceJob re-runs Places API while prospect audit is pending

| Field | Value |
|-------|-------|
| Subsystem | S4 Scoring & enrichment |
| Scores | M=2 R=2 L=2 O=3 → Total 9 (P2) |
| Evidence | `app/Jobs/ScorePlaceJob.php:39-45,66-80` — idempotency guard returns only when audit is **not** pending |
| Spec gap | [2026-05-27-gbp-scoring-flags-design.md](../specs/2026-05-27-gbp-scoring-flags-design.md) — retry semantics not documented |
| PR slice | Medium — "S4: skip ScorePlaceJob when prospect exists (match pending guard inversion)" |
| Risk | Medium — duplicate Places calls and `updateOrCreate` during active site audit; may reset fields mid-pipeline |
| Effort | ~1–2 hours |
| Notes | Guard comment implies early return when prospect exists; condition only skips when audit is complete/failed/skipped. |

#### REF-S4-02: GbpScoringService decomposition candidate (292 lines)

| Field | Value |
|-------|-------|
| Subsystem | S4 Scoring & enrichment |
| Scores | M=2 R=1 L=2 O=1 → Total 6 (P3) |
| Evidence | `app/Services/GbpScoringService.php` (292 lines); appendix file-size signal #5 |
| Spec gap | None |
| PR slice | Medium — "S4: extract GbpRelativeScorer from GbpScoringService" |
| Risk | Low — covered by `GbpScoringServiceTest` and `ScorePlaceJobWebsiteDiscoveryTest` |
| Effort | ~3–4 hours |
| Notes | Cohesive but dense: absolute scoring, relative benchmark scoring, flag dedup in `mergeScores`, `overlayProspectFields`, weak-host detection. Natural split: absolute vs relative scorers. |

#### REF-S4-03: ProspectEnrichmentService bypasses ProspectAuditService for audit dispatch

| Field | Value |
|-------|-------|
| Subsystem | S4 Scoring & enrichment |
| Scores | M=2 R=1 L=2 O=2 → Total 7 (P2) |
| Evidence | `app/Services/ProspectEnrichmentService.php:62-73`; contrast `app/Services/ProspectAuditService.php:15-39` |
| Spec gap | [2026-05-28-prospect-enrichment-design.md](../specs/2026-05-28-prospect-enrichment-design.md) — enrichment triggers audit; does not mandate service entry point |
| PR slice | Medium — "S4: route ProspectEnrichmentService audit queue through ProspectAuditService" |
| Risk | Medium — duplicated reset/dispatch logic; future guard changes must be applied in two places |
| Effort | ~1 hour |
| Notes | Inlines `auditResetFields()` merge and direct `AuditSiteJob::dispatch` instead of `queueSiteAudit()`. Pending guard duplicated at lines 25–28. |

#### REF-S4-04: CombineScoresJob retry can re-dispatch GenerateProspectReportJob

| Field | Value |
|-------|-------|
| Subsystem | S4 Scoring & enrichment |
| Scores | M=2 R=2 L=2 O=2 → Total 8 (P2) |
| Evidence | `app/Jobs/CombineScoresJob.php:36-38,56-61` — early return only when `audit_status === 'complete'`; report dispatch is not idempotent |
| Spec gap | None |
| PR slice | Medium — "S4: guard CombineScoresJob report dispatch (idempotent completion marker)" |
| Risk | Medium — queue retry after report dispatch but before status persist could duplicate reports/screenshots |
| Effort | ~1–2 hours |
| Notes | Job chain coupling: `ScorePlaceJob` → `AuditSiteJob` → `CombineScoresJob` → `GenerateProspectReportJob`. Failure propagation to search status is correct via `SearchStatusService`. |

#### REF-S4-05: CmsDetectionRunnerService uses `audit_driver` not a CMS-specific config seam

| Field | Value |
|-------|-------|
| Subsystem | S4 Scoring & enrichment |
| Scores | M=2 R=1 L=2 O=2 → Total 7 (P2) |
| Evidence | `app/Services/CmsDetectionRunnerService.php:19-22`; contrast `app/Services/ScreenshotCaptureService.php:22-30` (`screenshot_driver`) |
| Spec gap | [2026-06-01-cms-detection-design.md](../specs/2026-06-01-cms-detection-design.md) — driver routing not specified separately from audit |
| PR slice | Medium — "S4: add scanner.cms_detect_driver config (http vs playwright)" |
| Risk | Medium — local Playwright audit + Fly HTTP CMS (or vice versa) cannot be configured independently |
| Effort | ~2 hours |

#### REF-S4-06: DetectCmsJob failures log only — no operator-visible error surface

| Field | Value |
|-------|-------|
| Subsystem | S4 Scoring & enrichment |
| Scores | M=1 R=1 L=2 O=2 → Total 6 (P3) |
| Evidence | `app/Jobs/DetectCmsJob.php:49-56`; contrast `app/Services/AuditErrorRecorder.php` used by audit jobs |
| Spec gap | [2026-06-01-cms-detection-design.md](../specs/2026-06-01-cms-detection-design.md) — pending CMS UI state documented; failure state not |
| PR slice | Medium — "S4: persist DetectCmsJob failure on prospect or audit_jobs row" |
| Risk | Low — operator sees `cms.pending = true` indefinitely after silent job failure |
| Effort | ~2 hours |
| Notes | `DetectCmsJobTest` covers success/skip paths. `BackfillCmsCommand` dry-run pattern is good (matches repair commands). |

#### REF-S4-07: Violation impact counting duplicated in A11yScoringService and ReportBuilderService

| Field | Value |
|-------|-------|
| Subsystem | S4 Scoring & enrichment |
| Scores | M=2 R=1 L=2 O=1 → Total 6 (P3) |
| Evidence | `app/Services/A11yScoringService.php:24-32`; `app/Services/ReportBuilderService.php:351-364` (`summarizeViolations`) |
| Spec gap | None |
| PR slice | Medium — "S4/S5: extract shared ViolationCounter from scoring and report builders" |
| Risk | Low — drift if impact weights change in one path only |
| Effort | ~1–2 hours |

#### REF-S4-08: BenchmarkNormalizer field extraction overlaps GbpScoringService::extractFields

| Field | Value |
|-------|-------|
| Subsystem | S4 Scoring & enrichment |
| Scores | M=2 R=1 L=2 O=1 → Total 6 (P3) |
| Evidence | `app/Services/BenchmarkNormalizer.php:18-28`; `app/Services/GbpScoringService.php:42-54` |
| Spec gap | None |
| PR slice | Medium — "S4: share Places field normalisation between benchmark and scoring" |
| Risk | Low — `GenerateProspectReportJob` and `GbpScoringService` could drift on new Places fields |
| Effort | ~1–2 hours |

### S5 — Reports & public surfaces

#### REF-S5-01: ReportBuilderService decomposition candidate (457 lines)

| Field | Value |
|-------|-------|
| Subsystem | S5 Reports & public surfaces |
| Scores | M=3 R=1 L=2 O=2 → Total 8 (P2) |
| Evidence | `app/Services/ReportBuilderService.php` (457 lines); appendix #1 file-size signal |
| Spec gap | None — [2026-05-27-prospect-site-audit-detail-design.md](../specs/2026-05-27-prospect-site-audit-detail-design.md) intentionally centralises shaping in this service |
| PR slice | Medium — "S5: extract ViolationMapper, OperatorPageSpeedBuilder, CmsLabelResolver from ReportBuilderService" |
| Risk | Low — covered by `ReportBuilderServiceTest`, `GenerateProspectReportJobTest`, `ProspectShowTest` |
| Effort | ~4–6 hours |
| Notes | Natural extraction units: `mapViolations` / `summarizeViolations` (shared with S4), `buildOperatorPageSpeed` + metric shapers, `cmsForProspect` label/badge helpers, public `build()` benchmark section. Highest-priority decomposition in codebase. |

#### REF-S5-02: PublicReportController re-shapes stored report_data with inline fallbacks

| Field | Value |
|-------|-------|
| Subsystem | S5 Reports & public surfaces |
| Scores | M=2 R=1 L=2 O=2 → Total 7 (P2) |
| Evidence | `app/Http/Controllers/PublicReportController.php:38-79` — ~40 lines assembling Inertia props from `report_data` + live model fields |
| Spec gap | [2026-05-27-prospect-site-audit-detail-design.md](../specs/2026-05-27-prospect-site-audit-detail-design.md) — spec routes shaping through `ReportBuilderService`; public page bypasses it |
| PR slice | Medium — "S5: add ReportBuilderService::buildPublicProps or read stored snapshot only" |
| Risk | Medium — stale or inconsistent props if `report_data` and live prospect diverge; F1 prop-bloat cross-ref |
| Effort | ~2–3 hours |
| Notes | `combined_score` and `performance_score` read live from `$report->prospect` (lines 58–59) while `grade` / `grade_label` come from stored JSON (56–57). |

#### REF-S5-03: ReportDashboardController loads all reports without pagination

| Field | Value |
|-------|-------|
| Subsystem | S5 Reports & public surfaces |
| Scores | M=2 R=2 L=2 O=1 → Total 7 (P2) |
| Evidence | `app/Http/Controllers/ReportDashboardController.php:38-46,48-72` — four stat clones plus unbounded `->get()` |
| Spec gap | None |
| PR slice | Medium — "S5: paginate ReportDashboardController and consolidate stats query" |
| Risk | Low — grows with report volume per operator; eager load on `outreachEmails` is correct |
| Effort | ~2 hours |
| Notes | Inline `$request->only(['niche', 'viewed', 'warm'])` filter — Form Request gap (read-only index). No `ProspectReportPolicy`; scoping via `whereHas` user_id only. |

#### REF-S5-04: GenerateProspectReportJob re-dispatches CaptureScreenshotJob on every run

| Field | Value |
|-------|-------|
| Subsystem | S5 Reports & public surfaces |
| Scores | M=2 R=2 L=2 O=2 → Total 8 (P2) |
| Evidence | `app/Jobs/GenerateProspectReportJob.php:47-61` — `updateOrCreate` then unconditional `CaptureScreenshotJob::dispatch` |
| Spec gap | None |
| PR slice | Medium — "S5: skip CaptureScreenshotJob when desktop screenshot_paths already set" |
| Risk | Medium — job retry or manual re-generation queues duplicate Fly captures (links REF-S3-02) |
| Effort | ~1 hour |
| Notes | `tries = 2` with no idempotency guard. Token preserved on update (`$existing?->token`). |

#### REF-S5-05: ReportBuilderService uses service locator for CombineScoresService

| Field | Value |
|-------|-------|
| Subsystem | S5 Reports & public surfaces |
| Scores | M=1 R=1 L=2 O=1 → Total 5 (P3) |
| Evidence | `app/Services/ReportBuilderService.php:19` — `app(CombineScoresService::class)` inside `build()` |
| Spec gap | None |
| PR slice | Medium — "S5: inject CombineScoresService into ReportBuilderService" |
| Risk | Low — complicates testing and hides dependency graph |
| Effort | ~0.5 hours |

#### REF-S5-06: Public report grade can desync from live combined_score

| Field | Value |
|-------|-------|
| Subsystem | S5 Reports & public surfaces |
| Scores | M=2 R=1 L=2 O=2 → Total 7 (P2) |
| Evidence | `app/Http/Controllers/PublicReportController.php:56-59`; `app/Services/ReportBuilderService.php:40-41,320-328` |
| Spec gap | None |
| PR slice | Medium — "S5: derive public grade from stored report_data only or regenerate on score change" |
| Risk | Medium — operator enrichment/rescore updates prospect but public page shows old grade with new score band colour (`Public.jsx:14` uses live `combined_score`) |
| Effort | ~1–2 hours |
| Notes | `Public.jsx` uses `gradeColor(report.combined_score)` while headline grade comes from snapshot JSON. |

#### REF-S5-07: Public.jsx duplicates audit section visibility logic from operator components

| Field | Value |
|-------|-------|
| Subsystem | S5 Reports & public surfaces |
| Scores | M=2 R=1 L=1 O=1 → Total 5 (P3) |
| Evidence | `resources/js/Pages/Report/Public.jsx:15-20`; operator shaping in `ReportBuilderService::buildOperatorAudit` |
| Spec gap | None — F1 light pass |
| PR slice | Medium — "F1/S5: share hasA11y/hasLighthouse helpers between public and operator audit UI" |
| Risk | Low — display-only drift |
| Effort | ~1 hour |

### S6 — Outreach

#### REF-S6-01: OutreachController queue mutations use inline validation

| Field | Value |
|-------|-------|
| Subsystem | S6 Outreach |
| Scores | M=2 R=1 L=2 O=1 → Total 6 (P3) |
| Evidence | `app/Http/Controllers/OutreachController.php:75-78,114-118` |
| Spec gap | None — cross-cutting Form Request gap (REF-S10-01) |
| PR slice | Medium — "S6: add StoreOutreachSelectionRequest and GenerateOutreachEmailsRequest" |
| Risk | Low |
| Effort | ~1 hour |
| Notes | `storeSelection()` and `generate()` inline `$request->validate()`. Contrast `ProspectIgnoreController` which uses `StoreIgnoredProspectRequest`. |

#### REF-S6-02: OutreachSelectionPolicy exists but is never registered or invoked

| Field | Value |
|-------|-------|
| Subsystem | S6 Outreach |
| Scores | M=2 R=2 L=2 O=2 → Total 8 (P2) |
| Evidence | `app/Policies/OutreachSelectionPolicy.php:10-13`; `app/Providers/AppServiceProvider.php:33-35` (no `Gate::policy`); `app/Http/Controllers/OutreachController.php:93-100` uses `ProspectPolicy::view` + manual `where user_id` |
| Spec gap | [2026-05-26-operator-ui-design.md](../specs/2026-05-26-operator-ui-design.md) — policy documented; never wired in provider |
| PR slice | Medium — "S6: register OutreachSelectionPolicy and authorize destroy/clear via selection model" |
| Risk | Medium — dead policy gives false confidence; `destroySelection` deletes by `prospect_id` without loading `OutreachSelection` for policy check (scoped query mitigates) |
| Effort | ~1 hour |

#### REF-S6-03: OutreachEmailController checks prospect view, not email ownership

| Field | Value |
|-------|-------|
| Subsystem | S6 Outreach |
| Scores | M=2 R=2 L=2 O=1 → Total 7 (P2) |
| Evidence | `app/Http/Controllers/OutreachEmailController.php:13,22` — `authorize('view', $outreachEmail->prospect)`; `app/Models/OutreachEmail.php:14` has `user_id` |
| Spec gap | None |
| PR slice | Medium — "S6: add OutreachEmailPolicy (user owns email row)" |
| Risk | Low — prospect is already user-scoped via search; edge case if email `user_id` diverges from prospect owner |
| Effort | ~1 hour |

#### REF-S6-04: GenerateOutreachEmailJob lacks idempotency on queue retry

| Field | Value |
|-------|-------|
| Subsystem | S6 Outreach |
| Scores | M=2 R=2 L=2 O=2 → Total 8 (P2) |
| Evidence | `app/Jobs/GenerateOutreachEmailJob.php:21,43-53` — `tries = 2`, unconditional `OutreachEmail::create` |
| Spec gap | None |
| PR slice | Medium — "S6: skip GenerateOutreachEmailJob when matching email exists (prospect + user + pitch_angle)" |
| Risk | Medium — retry duplicates Anthropic API spend and outreach email rows |
| Effort | ~1–2 hours |
| Notes | Job rethrows after log (line 59) so Laravel retry is active. No dedup guard unlike `ScorePlaceJob` idempotency pattern. |

#### REF-S6-05: GenerateOutreachEmailJob routed to auditing queue

| Field | Value |
|-------|-------|
| Subsystem | S6 Outreach |
| Scores | M=1 R=1 L=2 O=2 → Total 6 (P3) |
| Evidence | `app/Jobs/GenerateOutreachEmailJob.php:29` — `AuditingQueue::apply($this)`; contrast audit/report jobs in same queue |
| Spec gap | None |
| PR slice | Medium — "S6: route GenerateOutreachEmailJob to searches or dedicated outreach queue" |
| Risk | Low — Anthropic HTTP work competes with Fly audit/screenshot workers on managed `auditing` queue |
| Effort | ~0.5 hours |

#### REF-S6-06: OutreachController index() fat inline serialization

| Field | Value |
|-------|-------|
| Subsystem | S6 Outreach |
| Scores | M=2 R=1 L=2 O=1 → Total 6 (P3) |
| Evidence | `app/Http/Controllers/OutreachController.php:30-70` (155-line controller); `ProspectListResource` used only in `SavedProspectController` |
| Spec gap | [2026-05-29-outreach-queue-clickable-prospects-design.md](../specs/2026-05-29-outreach-queue-clickable-prospects-design.md) — v1 spec said backend payload unchanged; drift risk as fields grow |
| PR slice | Medium — "S6: extract outreach queue/email payload mapper or extend ProspectListResource" |
| Risk | Low — F1/REF-S10-04 cross-ref on Resource underuse |
| Effort | ~2 hours |
| Notes | `Index.jsx` correctly wraps queue chips in `Link` with `?from=outreach` per spec (lines 131–133). |

#### REF-S6-07: OutreachEmail model uses legacy `protected $casts`

| Field | Value |
|-------|-------|
| Subsystem | S6 Outreach |
| Scores | M=1 R=1 L=2 O=1 → Total 5 (P3) |
| Evidence | `app/Models/OutreachEmail.php:19-22` |
| Spec gap | None — see REF-S10-02 |
| PR slice | Small — batch with REF-S10-02 `casts()` migration |
| Risk | Low |
| Effort | ~0.5 hours (batch) |

### S7 — OAuth & MCP

#### REF-S7-01: McpController decomposition candidate (745 lines)

| Field | Value |
|-------|-------|
| Subsystem | S7 OAuth & MCP |
| Scores | M=3 R=1 L=2 O=2 → Total 8 (P2) |
| Evidence | `app/Http/Controllers/Api/McpController.php` (745 lines); appendix file-size signal #1 |
| Spec gap | None — [2026-06-01-mcp-scan-monitoring-design.md](../specs/2026-06-01-mcp-scan-monitoring-design.md) and [2026-06-02-mcp-progress-flow-design.md](../specs/2026-06-02-mcp-progress-flow-design.md) describe behaviour, not controller shape |
| PR slice | Medium — "S7: extract McpJsonRpcDispatcher and McpProgressStreamHandler from McpController" |
| Risk | Low — `McpScanToolsTest` (14 tests) covers tool surface |
| Effort | ~4–6 hours |
| Notes | Cohesive responsibilities: auth resolution, JSON-RPC routing, streamable SSE progress loop (lines 561–677), tool definitions, origin allowlist. Natural split: transport/auth vs dispatch vs streaming. |

#### REF-S7-02: OAuthServerController fat grant handler (288 lines)

| Field | Value |
|-------|-------|
| Subsystem | S7 OAuth & MCP |
| Scores | M=2 R=1 L=2 O=1 → Total 6 (P3) |
| Evidence | `app/Http/Controllers/OAuthServerController.php` (288 lines); appendix #7 file-size signal |
| Spec gap | None |
| PR slice | Medium — "S7: extract OAuth token grant actions (authorization_code, refresh, revoke)" |
| Risk | Low — OAuth flow covered by `McpScanToolsTest` and manual integration |
| Effort | ~3–4 hours |
| Notes | Five inline `$request->validate()` call sites (lines 27, 60, 135, 155, 212). PKCE, resource indicator, and refresh rotation logic inline in private methods. |

#### REF-S7-03: OAuthServerController uses inline validation on all mutating endpoints

| Field | Value |
|-------|-------|
| Subsystem | S7 OAuth & MCP |
| Scores | M=2 R=1 L=2 O=1 → Total 6 (P3) |
| Evidence | `app/Http/Controllers/OAuthServerController.php:27-30,60-69,135-139,155-162,212-218` |
| Spec gap | None — REF-S10-01 |
| PR slice | Medium — "S7: add OAuth Register/Token/Revoke Form Requests" |
| Risk | Low — OAuth endpoints are machine clients; validation rules are stable |
| Effort | ~2 hours |

#### REF-S7-04: No dedicated ProgressFlowService unit tests

| Field | Value |
|-------|-------|
| Subsystem | S7 OAuth & MCP |
| Scores | M=2 R=1 L=2 O=2 → Total 7 (P2) |
| Evidence | `app/Services/ProgressFlowService.php` (176 lines); `tests/Feature/McpScanToolsTest.php:198-260` exercises flow via MCP only; `tests/Feature/ProspectShowTest.php:168` asserts one prospect field |
| Spec gap | [2026-06-02-mcp-progress-flow-design.md](../specs/2026-06-02-mcp-progress-flow-design.md) — phase/step/message semantics documented; no direct service tests |
| PR slice | Medium — "S7: add ProgressFlowServiceTest (search phases, prospect steps, duration buckets)" |
| Risk | Low — MCP integration tests pass (`php artisan test --filter=Mcp` → 14 pass) |
| Effort | ~2 hours |
| Notes | `watch_search_progress` snapshot path in `McpSearchService` (lines 111–122) returns one-shot data; long-poll semantics live in controller stream handler. |

#### REF-S7-05: McpController streamProgressToolCall blocks worker up to 45s

| Field | Value |
|-------|-------|
| Subsystem | S7 OAuth & MCP |
| Scores | M=1 R=2 L=2 O=2 → Total 7 (P2) |
| Evidence | `app/Http/Controllers/Api/McpController.php:568-646` — `sleep(2)` loop until timeout or `search_complete` |
| Spec gap | [2026-06-02-mcp-progress-flow-design.md](../specs/2026-06-02-mcp-progress-flow-design.md) — bounded watch documented; worker occupancy not addressed |
| PR slice | Medium — "S7: document streamable watch worker cost or move watch loop to async notifier" |
| Risk | Low — streamable transport is opt-in; legacy JSON-RPC unaffected |
| Effort | ~2–3 hours |
| Notes | Matches `mcp-integration-guide.md` streamable progress notification design. Cloud worker concurrency may limit concurrent watches. |

#### REF-S7-06: OAuth access-token revocation on revoke endpoint ignores access_token hint

| Field | Value |
|-------|-------|
| Subsystem | S7 OAuth & MCP |
| Scores | M=2 R=1 L=2 O=2 → Total 7 (P2) |
| Evidence | `app/Http/Controllers/OAuthServerController.php:141-148` — only revokes when `token_type_hint === 'refresh_token'` |
| Spec gap | None — RFC 7009 allows best-effort; short TTL access tokens assumed |
| PR slice | Medium — "S7: document access-token revocation window or add JWT denylist" |
| Risk | Medium — revoked refresh family still leaves ~1h access token valid (`mcp-integration-guide.md:69`) |
| Effort | ~1–2 hours |

#### REF-S7-07: ConnectedAppsController uses manual user_id check instead of policy

| Field | Value |
|-------|-------|
| Subsystem | S7 OAuth & MCP |
| Scores | M=1 R=1 L=2 O=1 → Total 5 (P3) |
| Evidence | `app/Http/Controllers/Settings/ConnectedAppsController.php:45-47` — `AccessDeniedHttpException` vs `McpKeyController` policy pattern |
| Spec gap | None — REF-S10-03 |
| PR slice | Medium — "S7/S9: add OauthMcpRefreshTokenFamilyPolicy" |
| Risk | Low — check is correct; inconsistent with `UserMcpKeyPolicy` |
| Effort | ~1 hour |

### S8 — Booking & calendar

#### REF-S8-01: AgencyBookingSettingsController uses inline validation

| Field | Value |
|-------|-------|
| Subsystem | S8 Booking & calendar |
| Scores | M=2 R=1 L=2 O=1 → Total 6 (P3) |
| Evidence | `app/Http/Controllers/AgencyBookingSettingsController.php:15-30,45-48` |
| Spec gap | None — REF-S10-01 |
| PR slice | Medium — "S8: add UpdateAgencyBookingSettingsRequest and TestBookingConnectionRequest" |
| Risk | Low — sensitive `fastmail_app_password` validated inline; Form Request would centralise empty-password unset rule (line 32–34) |
| Effort | ~1 hour |

#### REF-S8-02: PublicReportBookingController CalDAV failures surface as unhandled 500

| Field | Value |
|-------|-------|
| Subsystem | S8 Booking & calendar |
| Scores | M=2 R=2 L=2 O=2 → Total 8 (P2) |
| Evidence | `app/Services/ReportBookingService.php:79-80` — `createEvent` inside transaction; `app/Services/Calendar/FastmailCalDavClient.php:233` throws `RequestException`; `PublicReportBookingController.php:39` returns raw JSON with no try/catch |
| Spec gap | [2026-06-04-report-booking-fastmail-design.md](../specs/2026-06-04-report-booking-fastmail-design.md) — operator test path documented; public book failure UX not specified |
| PR slice | Medium — "S8: map CalDAV errors to 503 JSON on POST /r/{token}/book" |
| Risk | Medium — prospect sees generic 500 when Fastmail is down; DB rolls back correctly but no retry guidance |
| Effort | ~1–2 hours |
| Notes | `ReportBookingTest` uses `FakeCalendarProvider`; no test for CalDAV failure path. Operator `testConnection` surfaces friendly message (AgencyBookingService.php:40-41). |

#### REF-S8-03: BookingServiceProvider binds stale AgencyBookingSetting singleton

| Field | Value |
|-------|-------|
| Subsystem | S8 Booking & calendar |
| Scores | M=2 R=1 L=2 O=2 → Total 7 (P2) |
| Evidence | `app/Providers/BookingServiceProvider.php:14-16` — `new FastmailCalDavProvider(AgencyBookingSetting::current())` at resolve time |
| Spec gap | None |
| PR slice | Medium — "S8: resolve AgencyBookingSetting inside FastmailCalDavProvider per call" |
| Risk | Medium — long-lived worker/request may use pre-update credentials after Settings save until container rebound |
| Effort | ~1 hour |
| Notes | `ReportBookingTest` binds `FakeCalendarProvider` directly, masking provider staleness in tests. |

#### REF-S8-04: slotIsAvailable recomputes full day slot list per booking attempt

| Field | Value |
|-------|-------|
| Subsystem | S8 Booking & calendar |
| Scores | M=1 R=2 L=2 O=2 → Total 7 (P2) |
| Evidence | `app/Services/Calendar/BookingAvailabilityService.php:82-94` — `slotIsAvailable()` calls `availableSlots()` for whole day; invoked from `ReportBookingService.php:56` on every POST |
| Spec gap | None |
| PR slice | Medium — "S8: add targeted slotIsAvailable check without full day generation" |
| Risk | Low — public endpoint is throttled; CalDAV busy fetch is the cost |
| Effort | ~1–2 hours |
| Notes | `BookingAvailabilityServiceTest` covers slot logic with fake calendar. |

#### REF-S8-05: FastmailCalDavClient decomposition candidate (247 lines)

| Field | Value |
|-------|-------|
| Subsystem | S8 Booking & calendar |
| Scores | M=2 R=1 L=2 O=1 → Total 6 (P3) |
| Evidence | `app/Services/Calendar/FastmailCalDavClient.php` (247 lines); appendix >200 lines list |
| Spec gap | None |
| PR slice | Medium — "S8: extract CalDavXmlParser from FastmailCalDavClient" |
| Risk | Low — booking feature is new; tests mock at provider boundary |
| Effort | ~2–3 hours |

#### REF-S8-06: AgencyBookingSetting and ReportBooking on legacy `$casts`

| Field | Value |
|-------|-------|
| Subsystem | S8 Booking & calendar |
| Scores | M=1 R=1 L=2 O=1 → Total 5 (P3) |
| Evidence | `app/Models/AgencyBookingSetting.php:23`; `app/Models/ReportBooking.php:24` |
| Spec gap | None — REF-S10-02 |
| PR slice | Small — batch with REF-S10-02 |
| Risk | Low |
| Effort | ~0.5 hours (batch) |

#### REF-S8-07: PublicBookingController TidyCal fallback bypasses native booking when enabled

| Field | Value |
|-------|-------|
| Subsystem | S8 Booking & calendar |
| Scores | M=2 R=1 L=2 O=2 → Total 7 (P2) |
| Evidence | `app/Http/Controllers/PublicBookingController.php:34-52` — resolves `report_data['booking_url']` or `config('scanner.report_booking_url')` with no `AgencyBookingService::nativeBookingActive()` check |
| Spec gap | [2026-06-04-report-booking-fastmail-design.md](../specs/2026-06-04-report-booking-fastmail-design.md) — inline native booking on report `#book`; standalone `/book` route not in v1 spec |
| PR slice | Medium — "S8: redirect /book to report inline picker when native booking active" |
| Risk | Medium — operator enables Fastmail but legacy TidyCal `/book` link in stored `report_data` still routes to embed |
| Effort | ~1 hour |
| Notes | `PublicBookingTest` covers TidyCal paths only; `ReportBookingTest` covers native JSON API. |

### S9 — Operator settings & data hygiene

#### REF-S9-01: SettingsController uses inline validation on three mutating actions

| Field | Value |
|-------|-------|
| Subsystem | S9 Operator settings & data hygiene |
| Scores | M=2 R=1 L=2 O=1 → Total 6 (P3) |
| Evidence | `app/Http/Controllers/SettingsController.php:61-65,75-77,88-90`; `app/Http/Controllers/Settings/McpKeyController.php:41-43,62-64` |
| Spec gap | None — REF-S10-01 |
| PR slice | Medium — "S9: add UpdateSettingsRequest, ScanNichesRequest, BootstrapNichesRequest" |
| Risk | Low — `bootstrapNiches` typed `REFRESH` confirm matches [2026-05-29-niche-settings-maintenance-design.md](../specs/2026-05-29-niche-settings-maintenance-design.md) |
| Effort | ~1–2 hours |

#### REF-S9-02: Settings niche maintenance queues heavy jobs without operator guardrails

| Field | Value |
|-------|-------|
| Subsystem | S9 Operator settings & data hygiene |
| Scores | M=2 R=2 L=2 O=2 → Total 8 (P2) |
| Evidence | `app/Http/Controllers/SettingsController.php:73-98` — `Artisan::queue('niches:scan')` and `niches:bootstrap --force` with flash-only feedback |
| Spec gap | [2026-05-29-niche-settings-maintenance-design.md](../specs/2026-05-29-niche-settings-maintenance-design.md) — "flash queued only; failures in Horizon/logs" is intentional |
| PR slice | Medium — "S9: add rate limit or confirm dialog for bootstrap on Settings" |
| Risk | Medium — accidental double-click queues duplicate Places jobs across all niche×city pairs |
| Effort | ~1–2 hours |
| Notes | Spec aligns with implementation; operator UX gap remains. `SettingsTest` covers happy path. |

#### REF-S9-03: PurgeExpiredProspectData lacks dry-run / `--execute` gate

| Field | Value |
|-------|-------|
| Subsystem | S9 Operator settings & data hygiene |
| Scores | M=2 R=1 L=2 O=2 → Total 7 (P2) |
| Evidence | `app/Console/Commands/PurgeExpiredProspectData.php:17-55` — always mutates; contrast `RepairAuditsCommand` dry-run pattern (S3 keep candidate) |
| Spec gap | None |
| PR slice | Medium — "S9: add scanner:purge-expired dry-run default with --execute" |
| Risk | Low — scheduled command; `PurgeExpiredProspectDataTest` covers behaviour |
| Effort | ~1–2 hours |

#### REF-S9-04: IgnoredProspectController destroy uses abort_unless instead of policy

| Field | Value |
|-------|-------|
| Subsystem | S9 Operator settings & data hygiene |
| Scores | M=1 R=1 L=2 O=1 → Total 5 (P3) |
| Evidence | `app/Http/Controllers/IgnoredProspectController.php:48` — manual `user_id` check; no `IgnoredProspectPolicy` |
| Spec gap | None — REF-S10-03 |
| PR slice | Medium — "S9: add IgnoredProspectPolicy for list/destroy" |
| Risk | Low — check is correct |
| Effort | ~1 hour |

#### REF-S9-05: ProspectExclusionService listForUser loads all rows without pagination

| Field | Value |
|-------|-------|
| Subsystem | S9 Operator settings & data hygiene |
| Scores | M=2 R=2 L=2 O=1 → Total 7 (P2) |
| Evidence | `app/Services/ProspectExclusionService.php:56-142` — `listForUser()` returns full collection; `IgnoredProspectController.php:30-35` passes `total` count only |
| Spec gap | None |
| PR slice | Medium — "S9: paginate ignored prospects index" |
| Risk | Low — grows with operator ignore volume |
| Effort | ~2 hours |

#### REF-S9-06: HandleInertiaRequests duplicates outreach flash props

| Field | Value |
|-------|-------|
| Subsystem | S9 Operator settings & data hygiene |
| Scores | M=1 R=1 L=2 O=1 → Total 5 (P3) |
| Evidence | `app/Http/Middleware/HandleInertiaRequests.php:37-45` shares `skipped` flash; `app/Http/Controllers/OutreachController.php:66-69` also passes page-local `flash` |
| Spec gap | None |
| PR slice | Medium — "S9: consolidate outreach flash into middleware or page-only props" |
| Risk | Low — 48-line middleware is not a god-object; minor duplication |
| Effort | ~0.5 hours |

#### REF-S9-07: UserSettingsService is thin wrapper — settings update bypasses service

| Field | Value |
|-------|-------|
| Subsystem | S9 Operator settings & data hygiene |
| Scores | M=1 R=1 L=1 O=1 → Total 4 (P4) |
| Evidence | `app/Services/UserSettingsService.php` (32 lines); `SettingsController.php:67-68` calls `$setting->update()` directly after `forUser()` |
| Spec gap | None |
| PR slice | Small — "S9: add UserSettingsService::update() for validation + persist seam" |
| Risk | Low |
| Effort | ~0.5 hours |

### S10 — Shared infrastructure

## Cross-cutting findings (S10)
## Frontend light pass (F1)
## Recommended PR schedule

## Appendix: automated signal sheet

### Test baseline

**Command:** `php artisan test 2>&1 | tee /tmp/audit-test-baseline.txt`

| Metric | Count |
|--------|------:|
| Total tests | 337 |
| Passed | 331 |
| Failed | 1 |
| Errors | 3 |
| Skipped | 2 |
| Risky | 3 |
| Assertions | 1261 |
| Duration | 5723 ms |

**Failures and errors:**

| Test class | Message |
|------------|---------|
| `Tests\Feature\ScanNichesCommandTest::test_skips_already_complete_scans_without_force` | Output does not contain "Skipped 1". |
| `Tests\Feature\DirectUrlScanJobTest::test_creates_prospect_with_gbp_when_place_found` | Too few arguments to function `App\Jobs\DirectUrlScanJob::handle()`, 5 passed in `tests/Feature/DirectUrlScanJobTest.php` on line 44 and exactly 6 expected |
| `Tests\Feature\DirectUrlScanJobTest::test_creates_prospect_without_gbp_when_not_found` | Too few arguments to function `App\Jobs\DirectUrlScanJob::handle()`, 5 passed in `tests/Feature/DirectUrlScanJobTest.php` on line 78 and exactly 6 expected |
| `Tests\Feature\DirectUrlScanJobTest::test_enriches_search_from_gbp_for_report_benchmarks` | Too few arguments to function `App\Jobs\DirectUrlScanJob::handle()`, 5 passed in `tests/Feature/DirectUrlScanJobTest.php` on line 158 and exactly 6 expected |

**Risky tests:** 3 (same three `DirectUrlScanJobTest` cases above — errored before assertions ran).

**Raw output:**

```
{"tool":"phpunit","result":"failed","tests":337,"passed":331,"assertions":1261,"duration_ms":5723,"failed":1,"failures":[{"test":"Tests\\Feature\\ScanNichesCommandTest::test_skips_already_complete_scans_without_force","file":"/Users/rosstweedie/Sites/nthdesigns-scanner/tests/Feature/ScanNichesCommandTest.php","line":60,"message":"Output does not contain \"Skipped 1\"."}],"errors":3,"error_details":[{"test":"Tests\\Feature\\DirectUrlScanJobTest::test_creates_prospect_with_gbp_when_place_found","file":"/Users/rosstweedie/Sites/nthdesigns-scanner/tests/Feature/DirectUrlScanJobTest.php","line":21,"message":"Too few arguments to function App\\Jobs\\DirectUrlScanJob::handle(), 5 passed in /Users/rosstweedie/Sites/nthdesigns-scanner/tests/Feature/DirectUrlScanJobTest.php on line 44 and exactly 6 expected"},{"test":"Tests\\Feature\\DirectUrlScanJobTest::test_creates_prospect_without_gbp_when_not_found","file":"/Users/rosstweedie/Sites/nthdesigns-scanner/tests/Feature/DirectUrlScanJobTest.php","line":62,"message":"Too few arguments to function App\\Jobs\\DirectUrlScanJob::handle(), 5 passed in /Users/rosstweedie/Sites/nthdesigns-scanner/tests/Feature/DirectUrlScanJobTest.php on line 78 and exactly 6 expected"},{"test":"Tests\\Feature\\DirectUrlScanJobTest::test_enriches_search_from_gbp_for_report_benchmarks","file":"/Users/rosstweedie/Sites/nthdesigns-scanner/tests/Feature/DirectUrlScanJobTest.php","line":119,"message":"Too few arguments to function App\\Jobs\\DirectUrlScanJob::handle(), 5 passed in /Users/rosstweedie/Sites/nthdesigns-scanner/tests/Feature/DirectUrlScanJobTest.php on line 158 and exactly 6 expected"}],"skipped":2,"risky":3}
```

### Pint dry-run

**Command:** `./vendor/bin/pint --test 2>&1 | tee /tmp/audit-pint-baseline.txt`

**Result:** FAIL — **104 files** need format fixes.

<details>
<summary>Files needing format fixes (104)</summary>

- `database/factories/ProspectFactory.php`
- `database/factories/ProspectReportFactory.php`
- `database/factories/OutreachEmailFactory.php`
- `database/factories/SearchFactory.php`
- `bootstrap/app.php`
- `app/Providers/HorizonServiceProvider.php`
- `app/Queries/ProspectListQuery.php`
- `app/Models/AuditJob.php`
- `app/Models/OutreachEmail.php`
- `app/Support/PlaywrightBrowsers.php`
- `app/Support/FailedScreenshotQuery.php`
- `app/Support/ScannerConfig.php`
- `app/Support/StaleAuditJobCloser.php`
- `app/Http/Middleware/HandleInertiaRequests.php`
- `app/Http/Resources/ProspectListResource.php`
- `app/Http/Requests/UpdateProspectRequest.php`
- `app/Http/Controllers/NicheScanController.php`
- `app/Http/Controllers/IgnoredProspectController.php`
- `app/Http/Controllers/SearchController.php`
- `app/Http/Controllers/ExportController.php`
- `app/Http/Controllers/ReportDashboardController.php`
- `app/Http/Controllers/SavedProspectController.php`
- `app/Http/Controllers/Api/McpController.php`
- `app/Http/Controllers/ProspectNoteController.php`
- `app/Jobs/GenerateOutreachEmailJob.php`
- `app/Jobs/CaptureScreenshotJob.php`
- `app/Jobs/ScrapeProspectsJob.php`
- `app/Jobs/AuditSiteJob.php`
- `app/Jobs/GenerateProspectReportJob.php`
- `app/Jobs/CombineScoresJob.php`
- `app/Jobs/ScorePlaceJob.php`
- `app/Jobs/DirectUrlScanJob.php`
- `app/Services/CloudflareBrowserService.php`
- `app/Services/ScreenshotCaptureService.php`
- `app/Services/BraveSearchService.php`
- `app/Services/WebsiteDiscoveryService.php`
- `app/Services/GooglePlacesService.php`
- `app/Services/OAuthMcpRefreshTokenService.php`
- `app/Services/GbpScoringService.php`
- `app/Services/ScreenshotStorageService.php`
- `app/Services/ProspectAuditService.php`
- `app/Services/Mcp/McpSingleSiteAuditService.php`
- `app/Services/ApiHealthService.php`
- `app/Services/ReportBuilderService.php`
- `app/Services/ProspectExclusionService.php`
- `app/Services/BrowserServiceClient.php`
- `app/Services/AuditErrorRecorder.php`
- `app/Services/AuditRunnerService.php`
- `app/Services/SearchStatusService.php`
- `app/Services/A11yScoringService.php`
- `app/Services/GoogleCustomSearchService.php`
- `app/Services/BenchmarkNormalizer.php`
- `app/Services/DirectUrlSearchEnrichment.php`
- `app/Services/GbpPlaceContextResolver.php`
- `app/Services/AnthropicService.php`
- `app/Console/Commands/NichesBootstrapCommand.php`
- `app/Console/Commands/BackfillWebsitesCommand.php`
- `app/Console/Commands/BackfillCmsCommand.php`
- `app/Console/Commands/BackfillAuditsCommand.php`
- `app/Console/Commands/PurgeExpiredProspectData.php`
- `config/horizon.php`
- `config/services.php`
- `config/scanner.php`
- `config/niches.php`
- `tests/Unit/AnthropicServiceTest.php`
- `tests/Unit/GbpPlaceContextResolverTest.php`
- `tests/Unit/PlaywrightBrowsersTest.php`
- `tests/Unit/StuckSiteAuditQueryTest.php`
- `tests/Unit/A11yScoringServiceTest.php`
- `tests/Unit/IncompleteAuditQueryTest.php`
- `tests/Unit/AuditErrorRecorderTest.php`
- `tests/Unit/BenchmarkNormalizerTest.php`
- `tests/Unit/WebsiteUrlNormalizerTest.php`
- `tests/Unit/GooglePlacesServiceTest.php`
- `tests/Unit/WebsiteDiscoveryServiceTest.php`
- `tests/Unit/GoogleCustomSearchServiceTest.php`
- `tests/Unit/FailedScreenshotQueryTest.php`
- `tests/Unit/GbpScoringServiceTest.php`
- `tests/Unit/DirectUrlSearchEnrichmentTest.php`
- `tests/Unit/ReportBuilderServiceTest.php`
- `tests/Unit/BraveSearchServiceTest.php`
- `tests/Unit/CombineScoresServiceTest.php`
- `tests/Unit/ProspectAuditServiceRepairTest.php`
- `tests/Unit/PlaywrightEnvTest.php`
- `tests/Unit/FailedSiteAuditQueryTest.php`
- `tests/Feature/ScorePlaceJobWebsiteDiscoveryTest.php`
- `tests/Feature/PublicBookingTest.php`
- `tests/Feature/OutreachSelectionTest.php`
- `tests/Feature/McpScanToolsTest.php`
- `tests/Feature/NicheExclusionServiceTest.php`
- `tests/Feature/SettingsTest.php`
- `tests/Feature/BackfillAuditsCommandTest.php`
- `tests/Feature/ProspectListQueryTest.php`
- `tests/Feature/AuditDriverTest.php`
- `tests/Feature/PurgeExpiredProspectDataTest.php`
- `tests/Feature/ProspectShowTest.php`
- `tests/Feature/ScrapeProspectsJobTest.php`
- `tests/Feature/DirectUrlScanJobTest.php`
- `tests/Feature/CmsDetectionIntegrationTest.php`
- `tests/Feature/BackfillWebsitesCommandTest.php`
- `tests/Feature/ProspectIgnoreTest.php`
- `tests/Feature/RepairAuditsCommandTest.php`
- `tests/Feature/SearchRateLimitTest.php`
- `tests/Feature/ExportProspectsTest.php`
- `tests/Feature/AutoGenerateReportTest.php`
- `tests/Feature/GenerateProspectReportJobTest.php`
- `tests/Feature/ProspectEnrichmentTest.php`
- `tests/Feature/ProspectReauditTest.php`

</details>

### File size signals

**Commands:**

```bash
wc -l app/Services/*.php app/Http/Controllers/*.php app/Jobs/*.php | sort -rn | head -20
find app -name "*.php" | wc -l
find resources/js \( -name "*.jsx" -o -name "*.js" \) | wc -l
find tests -name "*.php" | wc -l
```

**Top 20 (Services + Controllers + Jobs):**

```
    7293 total
     457 app/Services/ReportBuilderService.php
     316 app/Services/WebsiteDiscoveryService.php
     303 app/Services/BrowserServiceClient.php
     292 app/Services/GbpScoringService.php
     288 app/Http/Controllers/OAuthServerController.php
     257 app/Services/GooglePlacesService.php
     232 app/Services/OAuthMcpRefreshTokenService.php
     220 app/Http/Controllers/SearchController.php
     202 app/Http/Controllers/ProspectController.php
     196 app/Services/ApiHealthService.php
     176 app/Services/ProgressFlowService.php
     162 app/Services/NicheExclusionService.php
     155 app/Http/Controllers/OutreachController.php
     151 app/Services/OutreachEmailGeneratorService.php
     142 app/Services/ProspectExclusionService.php
     128 app/Jobs/ScorePlaceJob.php
     124 app/Jobs/ScanNicheJob.php
     124 app/Jobs/AuditSiteJob.php
     120 app/Http/Controllers/SettingsController.php
```

**File counts:**

| Scope | Count |
|-------|------:|
| `app/**/*.php` | 157 |
| `resources/js/**/*.{js,jsx}` | 94 |
| `tests/**/*.php` | 83 |

**All `app/` files >200 lines (14):**

| Lines | File |
|------:|------|
| 745 | `app/Http/Controllers/Api/McpController.php` |
| 457 | `app/Services/ReportBuilderService.php` |
| 342 | `app/Console/Commands/NichesBootstrapCommand.php` |
| 316 | `app/Services/WebsiteDiscoveryService.php` |
| 303 | `app/Services/BrowserServiceClient.php` |
| 292 | `app/Services/GbpScoringService.php` |
| 288 | `app/Http/Controllers/OAuthServerController.php` |
| 257 | `app/Services/GooglePlacesService.php` |
| 248 | `app/Services/Mcp/McpSearchService.php` |
| 248 | `app/Console/Commands/BackfillWebsitesCommand.php` |
| 247 | `app/Services/Calendar/FastmailCalDavClient.php` |
| 232 | `app/Services/OAuthMcpRefreshTokenService.php` |
| 220 | `app/Http/Controllers/SearchController.php` |
| 202 | `app/Http/Controllers/ProspectController.php` |

### Form Request coverage gap

**Command:** `find app/Http/Requests -name "*.php"`

**Form Request count:** 6

```
app/Http/Requests/StoreProspectNoteRequest.php
app/Http/Requests/Auth/LoginRequest.php
app/Http/Requests/ProfileUpdateRequest.php
app/Http/Requests/StoreIgnoredProspectRequest.php
app/Http/Requests/UpdateProspectRequest.php
app/Http/Requests/StoreDirectUrlSearchRequest.php
```

**Controllers with `store`/`update` methods:** 18

```
app/Http/Controllers/SettingsController.php
app/Http/Controllers/ProspectController.php
app/Http/Controllers/AgencyBookingSettingsController.php
app/Http/Controllers/PublicReportBookingController.php
app/Http/Controllers/ProspectIgnoreController.php
app/Http/Controllers/SearchController.php
app/Http/Controllers/Settings/McpKeyController.php
app/Http/Controllers/NicheIgnoreController.php
app/Http/Controllers/ProspectNoteController.php
app/Http/Controllers/ExportController.php
app/Http/Controllers/ProfileController.php
app/Http/Controllers/Auth/ConfirmablePasswordController.php
app/Http/Controllers/Auth/RegisteredUserController.php
app/Http/Controllers/Auth/AuthenticatedSessionController.php
app/Http/Controllers/Auth/EmailVerificationNotificationController.php
app/Http/Controllers/Auth/PasswordController.php
app/Http/Controllers/Auth/PasswordResetLinkController.php
app/Http/Controllers/Auth/NewPasswordController.php
```

**Inline `$request->validate(` usage:** 13 controllers (22 call sites)

| Controller | Lines |
|------------|-------|
| `SearchController.php` | 76 |
| `SettingsController.php` | 61, 75, 88 |
| `Settings/McpKeyController.php` | 41, 62 |
| `PublicReportBookingController.php` | 31 |
| `AgencyBookingSettingsController.php` | 15, 45 |
| `Auth/RegisteredUserController.php` | 34 |
| `Auth/NewPasswordController.php` | 37 |
| `Auth/PasswordResetLinkController.php` | 32 |
| `Auth/PasswordController.php` | 18 |
| `OAuthServerController.php` | 27, 60, 135, 155, 212 |
| `OutreachController.php` | 75, 114 |
| `NicheIgnoreController.php` | 13, 24 |
| `IgnoredProspectController.php` | 17 |

**Gap summary:** 6 Form Requests vs 18 store/update controllers and 13 controllers with inline validation. Only a subset of write endpoints use dedicated Form Request classes.

### Model casts migration status

**Commands:**

```bash
rg "protected \$casts" app/Models -l
rg "function casts\(" app/Models -l
```

**Legacy `protected $casts` (7 models):**

- `app/Models/AuditJobErrorDetail.php`
- `app/Models/OutreachEmail.php`
- `app/Models/AuditJob.php`
- `app/Models/ReportBooking.php`
- `app/Models/Prospect.php`
- `app/Models/AgencyBookingSetting.php`
- `app/Models/ProspectReport.php`

**Modern `casts()` method (8 models):**

- `app/Models/OauthMcpRefreshToken.php`
- `app/Models/OauthMcpClient.php`
- `app/Models/OauthMcpAuthorizationCode.php`
- `app/Models/NicheScan.php`
- `app/Models/UserMcpKey.php`
- `app/Models/User.php`
- `app/Models/Search.php`
- `app/Models/OauthMcpRefreshTokenFamily.php`

**Total models:** 22 — **7 still on legacy `$casts`**, **8 migrated**, **7 with no explicit casts** (`IgnoredProspect`, `NicheInclusionOverride`, `IgnoredNiche`, `ProspectNote`, `UserSetting`, `Export`, `OutreachSelection`).

### Support vs Queries overlap

**`app/Support/` (19 files):**

```
AxeViolationCopy.php
AuditingQueue.php
AuditingQueuePresence.php
BearerTokenExtractor.php
FailedScreenshotQuery.php
FailedSiteAuditQuery.php
IncompleteAuditQuery.php
NicheQueue.php
OAuthMcpPemLoader.php
PlaywrightBrowsers.php
PlaywrightEnv.php
QueueDispatchDelay.php
RepairAuditScope.php
ScannerConfig.php
SearchQueue.php
StaleAuditJobCloser.php
StuckSiteAuditQuery.php
TidyCalEmbed.php
WebsiteUrlNormalizer.php
```

**`app/Queries/` (1 file):**

```
ProspectListQuery.php
```

**Responsibilities:**

- **`app/Support/`** — mixed bag: queue naming/dispatch helpers, Playwright/browser config, OAuth PEM loading, repair/audit scope utilities, URL normalization, and **four Eloquent query builders** (`StuckSiteAuditQuery`, `IncompleteAuditQuery`, `FailedSiteAuditQuery`, `FailedScreenshotQuery`) for audit-repair workflows.
- **`app/Queries/`** — single user-scoped prospect listing query with filter application (`ProspectListQuery`).

**Overlap notes:** Query-builder classes are split across namespaces — audit-repair queries live under `Support/` while prospect listing lives under `Queries/`. Both follow a similar static/instance query-object pattern but are organized inconsistently. Consolidating query objects under `app/Queries/` (or a single convention) would reduce navigation friction.
