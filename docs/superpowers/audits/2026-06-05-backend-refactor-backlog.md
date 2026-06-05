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
### S2 — Niche scanning
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
