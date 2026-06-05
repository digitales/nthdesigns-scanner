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
### S4 — Scoring & enrichment
### S5 — Reports & public surfaces
### S6 — Outreach
### S7 — OAuth & MCP
### S8 — Booking & calendar
### S9 — Operator settings & data hygiene
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
