# Laravel 13 Modernisation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Align the nthdesigns Prospect Scanner backend with Laravel 13 idioms — backed enums, `Queue::route()`, job PHP attributes, and honest `OpenRouterService` naming — without changing product behaviour or the OpenRouter integration.

**Architecture:** Four waves across 10 PRs. Wave 1 is mechanical (rename, queue routing, attributes, Pint). Waves 2–3 introduce `app/Enums/` with model casts and replace string literals in `app/`. Wave 4 aligns Form Request validation and audits frontend string comparisons. No DB migrations; enum values match existing column constraints.

**Tech Stack:** Laravel 13.11, PHP 8.3, PHPUnit 12, Pint, Inertia (frontend string pass only in W4).

**Spec:** `docs/superpowers/specs/2026-06-08-laravel-13-modernisation-design.md`

---

## File map

| Path | Responsibility |
|------|----------------|
| `app/Services/OpenRouterService.php` | OpenRouter chat completions (renamed from AnthropicService) |
| `app/Services/OutreachEmailGeneratorService.php` | Injects OpenRouterService |
| `app/Providers/AppServiceProvider.php` | `Queue::route()` registration |
| `app/Support/{Search,Niche,Auditing}Queue.php` | Queue name + connection helpers; `apply()` removed after W1 |
| `app/Enums/*.php` | 14 backed string enums |
| `app/Models/{Search,Prospect,AuditJob,NicheScan,OutreachEmail,IgnoredProspect,IgnoredNiche}.php` | Enum casts |
| `app/Jobs/*.php` | Attributes + constructor cleanup |
| `app/Http/Requests/*.php` | `Rule::enum()` in W4 |
| `app/Services/SearchStatusService.php` | SearchStatus + AuditStatus enum usage |
| `app/Services/ProgressFlowService.php` | SearchStatus enum usage |
| `app/Queries/*Audit*.php` | AuditStatus / AuditJobStatus enum usage |
| `tests/Unit/OpenRouterServiceTest.php` | Renamed service tests |
| `tests/Unit/Enums/` | Enum cast round-trip tests (create in W2) |

---

## Verification baseline

Before starting any task, confirm green baseline:

```bash
cd /Users/rosstweedie/Sites/nthdesigns-scanner
php artisan test
```

Expected: `431 passed`, `2 skipped`.

After each task commit, re-run full suite unless the task specifies a narrower filter.

---

### Task 1: Rename AnthropicService → OpenRouterService (PR 1, Wave 1)

**Files:**
- Rename: `app/Services/AnthropicService.php` → `app/Services/OpenRouterService.php`
- Modify: `app/Services/OutreachEmailGeneratorService.php`
- Rename: `tests/Unit/AnthropicServiceTest.php` → `tests/Unit/OpenRouterServiceTest.php`

- [ ] **Step 1: Rename service class**

Rename file and update class name. Content is identical except the class declaration:

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenRouterService
{
    // ... keep existing constructor and complete() body unchanged ...
}
```

- [ ] **Step 2: Update OutreachEmailGeneratorService injection**

```php
// app/Services/OutreachEmailGeneratorService.php
public function __construct(
    private OpenRouterService $openRouter,
    private AgencyBookingService $agencyBooking,
) {}
```

Find the call site inside `generate()` (currently `$this->anthropic->complete(...)`) and change to `$this->openRouter->complete(...)`.

- [ ] **Step 3: Rename and update test**

```php
<?php

namespace Tests\Unit;

use App\Services\OpenRouterService;
// ... rest unchanged, replace AnthropicService with OpenRouterService in assertions ...
```

Rename test class to `OpenRouterServiceTest`.

- [ ] **Step 4: Verify no app/test references remain**

```bash
rg 'AnthropicService' app tests
```

Expected: no matches.

- [ ] **Step 5: Run tests**

```bash
php artisan test --filter=OpenRouterServiceTest
php artisan test --filter=OutreachGenerateTest
```

Expected: all PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Services/OpenRouterService.php app/Services/OutreachEmailGeneratorService.php tests/Unit/OpenRouterServiceTest.php
git rm app/Services/AnthropicService.php tests/Unit/AnthropicServiceTest.php 2>/dev/null || true
git commit -m "$(cat <<'EOF'
Rename AnthropicService to OpenRouterService.

Aligns service naming with OpenRouter transport and config keys without changing outreach behaviour.
EOF
)"
```

---

### Task 2: Queue::route() centralisation (PR 2, Wave 1)

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `app/Jobs/AuditSiteJob.php`, `CaptureScreenshotJob.php`, `CombineScoresJob.php`, `DetectCmsJob.php`, `DirectUrlScanJob.php`, `GenerateOutreachEmailJob.php`, `GenerateProspectReportJob.php`, `ScanNicheJob.php`, `ScorePlaceJob.php`, `ScrapeProspectsJob.php`
- Modify: `app/Support/SearchQueue.php`, `NicheQueue.php`, `AuditingQueue.php`
- Modify: `app/Http/Controllers/NicheScanSampleController.php`, `app/Console/Commands/ScanNichesCommand.php` (optional: simplify dispatch)
- Test: `tests/Feature/GenerateOutreachEmailJobTest.php`, `tests/Feature/ScanNichesCommandTest.php`, `tests/Unit/AuditingQueueTest.php`

- [ ] **Step 1: Register routes in AppServiceProvider**

Add imports and call at end of `boot()` (before closing brace):

```php
use App\Jobs\AuditSiteJob;
use App\Jobs\CaptureScreenshotJob;
use App\Jobs\CombineScoresJob;
use App\Jobs\DetectCmsJob;
use App\Jobs\DirectUrlScanJob;
use App\Jobs\GenerateOutreachEmailJob;
use App\Jobs\GenerateProspectReportJob;
use App\Jobs\ScanNicheJob;
use App\Jobs\ScorePlaceJob;
use App\Jobs\ScrapeProspectsJob;
use App\Support\AuditingQueue;
use App\Support\NicheQueue;
use App\Support\SearchQueue;
use Illuminate\Support\Facades\Queue;

// Inside boot():
Queue::route([
    DirectUrlScanJob::class => [SearchQueue::NAME, config('scanner.search_queue_connection')],
    ScrapeProspectsJob::class => [SearchQueue::NAME, config('scanner.search_queue_connection')],
    ScorePlaceJob::class => [SearchQueue::NAME, config('scanner.search_queue_connection')],
    GenerateOutreachEmailJob::class => [SearchQueue::NAME, config('scanner.search_queue_connection')],

    ScanNicheJob::class => [NicheQueue::NAME, config('scanner.niche_queue_connection')],

    AuditSiteJob::class => [AuditingQueue::NAME, config('scanner.auditing_queue_connection')],
    CaptureScreenshotJob::class => [AuditingQueue::NAME, config('scanner.auditing_queue_connection')],
    CombineScoresJob::class => [AuditingQueue::NAME, config('scanner.auditing_queue_connection')],
    DetectCmsJob::class => [AuditingQueue::NAME, config('scanner.auditing_queue_connection')],
    GenerateProspectReportJob::class => [AuditingQueue::NAME, config('scanner.auditing_queue_connection')],
]);
```

- [ ] **Step 2: Remove constructor queue wiring from all 10 jobs**

For each job listed above, delete the constructor body that calls `SearchQueue::apply($this)`, `NicheQueue::apply($this)`, or `AuditingQueue::apply($this)`.

Examples:

```php
// AuditSiteJob — remove AuditingQueue import and constructor entirely if only queue wiring:
public function __construct(public Prospect $prospect) {}

// ScanNicheJob — keep string params constructor, remove NicheQueue::apply($this) line only
```

`DetectCmsJob` keeps `public function __construct(public Prospect $prospect, bool $force = false)` but removes `AuditingQueue::apply($this)`.

- [ ] **Step 3: Remove `apply()` from queue support classes**

In `SearchQueue.php`, `NicheQueue.php`, `AuditingQueue.php`, delete the `apply()` method. Keep `NAME`, `connection()`, `dispatch()`, and `chain()`.

Update `dispatch()` to stop calling `apply()`:

```php
public static function dispatch(object $job): PendingDispatch
{
    return dispatch($job);
}
```

`Queue::route()` now handles connection/queue assignment.

- [ ] **Step 4: Run queue-related tests**

```bash
php artisan test --filter=GenerateOutreachEmailJobTest
php artisan test --filter=ScanNichesCommandTest
php artisan test --filter=AuditingQueueTest
php artisan test --filter=RepairAuditsCommandTest
```

Expected: all PASS; assertions on `$job->queue` and `$job->connection` still match `SearchQueue::NAME` / `NicheQueue::NAME` / `AuditingQueue::NAME`.

- [ ] **Step 5: Run full suite**

```bash
php artisan test
```

Expected: 431 passed, 2 skipped.

- [ ] **Step 6: Commit**

```bash
git add app/Providers/AppServiceProvider.php app/Jobs/ app/Support/SearchQueue.php app/Support/NicheQueue.php app/Support/AuditingQueue.php
git commit -m "$(cat <<'EOF'
Centralise job queue routing with Queue::route().

Removes per-job constructor queue wiring while preserving hybrid search/niche/auditing connections.
EOF
)"
```

---

### Task 3: Job PHP attributes (PR 3, Wave 1)

**Files:**
- Modify: all 10 scanner jobs above + `app/Jobs/SendReportBookingConfirmationJob.php`

Preserve existing retry/timeout values from current properties (do not change behaviour).

| Job | Remove properties | Add attributes |
|-----|-------------------|----------------|
| `AuditSiteJob` | `$tries`, `$timeout` | `#[Tries(2)]`, `#[Timeout(240)]`, `#[WithoutRelations]` on `$prospect` |
| `CaptureScreenshotJob` | `$tries`, `$timeout` | `#[Tries(3)]`, `#[Timeout(180)]`, `#[WithoutRelations]` on `$report` |
| `CombineScoresJob` | `$tries` | `#[Tries(3)]`, `#[WithoutRelations]` on `$prospect` |
| `DetectCmsJob` | `$tries`, `$timeout` | `#[Tries(2)]`, `#[Timeout(120)]`, `#[WithoutRelations]` on `$prospect` |
| `DirectUrlScanJob` | — | `#[WithoutRelations]` on `$search` if model passed |
| `GenerateOutreachEmailJob` | `$tries`, `$timeout` | `#[Tries(2)]`, `#[Timeout(90)]`, `#[WithoutRelations]` on `$prospect` and `$user` |
| `GenerateProspectReportJob` | `$tries` | `#[Tries(2)]`, `#[WithoutRelations]` on `$prospect` |
| `ScanNicheJob` | `$tries` | `#[Tries(3)]` only (no Eloquent models in constructor) |
| `ScorePlaceJob` | — | `#[WithoutRelations]` on `$search` / models if present |
| `ScrapeProspectsJob` | `$tries` | `#[Tries(3)]`, `#[WithoutRelations]` on `$search` |
| `SendReportBookingConfirmationJob` | `$tries` | `#[Tries(3)]` (no model serialisation — uses `int $bookingId`) |

- [ ] **Step 1: Apply attributes to AuditSiteJob (template)**

```php
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Attributes\WithoutRelations;

#[Tries(2)]
#[Timeout(240)]
class AuditSiteJob implements ShouldQueue
{
    // ...
    public function __construct(
        #[WithoutRelations]
        public Prospect $prospect,
    ) {}
```

Keep `public int $backoff = 60;` as a property.

- [ ] **Step 2: Repeat for remaining jobs**

Apply the table above. Import attributes from `Illuminate\Queue\Attributes\*`.

- [ ] **Step 3: Run job tests**

```bash
php artisan test --filter=CaptureScreenshotJobTest
php artisan test --filter=DirectUrlScanJobTest
php artisan test --filter=GenerateProspectReportJobTest
php artisan test --filter=AuditDriverTest
php artisan test --filter=ReportBookingTest
```

Expected: all PASS.

- [ ] **Step 4: Full suite + commit**

```bash
php artisan test
git add app/Jobs/
git commit -m "$(cat <<'EOF'
Migrate job retry and timeout config to Laravel 13 attributes.

Uses Tries, Timeout, and WithoutRelations without changing queue behaviour.
EOF
)"
```

---

### Task 4: Pint cleanup (PR 4, Wave 1)

**Files:**
- Modify: `tests/Unit/GooglePlacesServiceTest.php`, `tests/Unit/BraveSearchServiceTest.php`, `tests/Unit/ApiUsageLimiterTest.php`

- [ ] **Step 1: Run Pint**

```bash
./vendor/bin/pint tests/Unit/GooglePlacesServiceTest.php tests/Unit/BraveSearchServiceTest.php tests/Unit/ApiUsageLimiterTest.php
```

- [ ] **Step 2: Verify Pint clean**

```bash
./vendor/bin/pint --test
```

Expected: PASS (0 files needing fixes).

- [ ] **Step 3: Commit**

```bash
git add tests/Unit/GooglePlacesServiceTest.php tests/Unit/BraveSearchServiceTest.php tests/Unit/ApiUsageLimiterTest.php
git commit -m "$(cat <<'EOF'
Apply Pint fixes to remaining test files.
EOF
)"
```

**Wave 1 checkpoint:** Run `php artisan test`. Deploy to staging and verify hybrid queues (`SEARCH_QUEUE_CONNECTION=database`, `AUDITING_QUEUE_CONNECTION=cloud`) before starting Wave 2.

---

### Task 5: Core enums — SearchStatus, ScanType, SearchSource (PR 5, Wave 2)

**Files:**
- Create: `app/Enums/SearchStatus.php`, `ScanType.php`, `SearchSource.php`
- Create: `tests/Unit/Enums/SearchModelCastsTest.php`
- Modify: `app/Models/Search.php`

- [ ] **Step 1: Write failing enum cast test**

```php
<?php

namespace Tests\Unit\Enums;

use App\Enums\ScanType;
use App\Enums\SearchSource;
use App\Enums\SearchStatus;
use App\Models\Search;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchModelCastsTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_status_and_scan_type_cast_to_enums(): void
    {
        $search = Search::factory()->create([
            'user_id' => User::factory()->create()->id,
            'status' => 'auditing',
            'scan_type' => 'combined',
            'source' => 'discovery',
        ]);

        $search->refresh();

        $this->assertInstanceOf(SearchStatus::class, $search->status);
        $this->assertSame(SearchStatus::Auditing, $search->status);
        $this->assertInstanceOf(ScanType::class, $search->scan_type);
        $this->assertSame(ScanType::Combined, $search->scan_type);
        $this->assertInstanceOf(SearchSource::class, $search->source);
        $this->assertSame(SearchSource::Discovery, $search->source);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test --filter=SearchModelCastsTest
```

Expected: FAIL (status is string, not enum).

- [ ] **Step 3: Create enum classes**

```php
<?php
// app/Enums/SearchStatus.php
namespace App\Enums;

enum SearchStatus: string
{
    case Pending = 'pending';
    case Discovering = 'discovering';
    case Auditing = 'auditing';
    case Complete = 'complete';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return $this === self::Complete || $this === self::Failed;
    }
}
```

```php
<?php
// app/Enums/ScanType.php
namespace App\Enums;

enum ScanType: string
{
    case GbpOnly = 'gbp_only';
    case AccessibilityOnly = 'accessibility_only';
    case Combined = 'combined';
}
```

```php
<?php
// app/Enums/SearchSource.php
namespace App\Enums;

enum SearchSource: string
{
    case Discovery = 'discovery';
    case DirectUrl = 'direct_url';
}
```

- [ ] **Step 4: Add casts and update isDirectUrl()**

```php
// app/Models/Search.php
use App\Enums\ScanType;
use App\Enums\SearchSource;
use App\Enums\SearchStatus;

protected function casts(): array
{
    return [
        'benchmark_snapshot' => 'array',
        'status' => SearchStatus::class,
        'scan_type' => ScanType::class,
        'source' => SearchSource::class,
    ];
}

public function isDirectUrl(): bool
{
    return $this->source === SearchSource::DirectUrl;
}
```

- [ ] **Step 5: Run test and fix factories**

```bash
php artisan test --filter=SearchModelCastsTest
```

If `SearchFactory` sets raw strings, that is fine — casts accept string values. Fix any factory using invalid enum values.

- [ ] **Step 6: Commit**

```bash
git add app/Enums/ app/Models/Search.php tests/Unit/Enums/
git commit -m "$(cat <<'EOF'
Add SearchStatus, ScanType, and SearchSource enums with model casts.
EOF
)"
```

---

### Task 6: Core enums — AuditStatus, AuditJobStatus, AuditJobType (PR 6, Wave 2)

**Files:**
- Create: `app/Enums/AuditStatus.php`, `AuditJobStatus.php`, `AuditJobType.php`
- Create: `tests/Unit/Enums/ProspectAuditCastsTest.php`
- Modify: `app/Models/Prospect.php`, `app/Models/AuditJob.php`

- [ ] **Step 1: Write failing cast test**

```php
public function test_prospect_audit_status_casts_to_enum(): void
{
    $prospect = Prospect::factory()->create(['audit_status' => 'pending']);
    $prospect->refresh();

    $this->assertInstanceOf(AuditStatus::class, $prospect->audit_status);
    $this->assertSame(AuditStatus::Pending, $prospect->audit_status);
}
```

- [ ] **Step 2: Create enums**

```php
// AuditStatus.php
enum AuditStatus: string
{
    case Pending = 'pending';
    case Complete = 'complete';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function isPending(): bool
    {
        return $this === self::Pending;
    }
}

// AuditJobStatus.php
enum AuditJobStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Complete = 'complete';
    case Failed = 'failed';
}

// AuditJobType.php
enum AuditJobType: string
{
    case GbpScore = 'gbp_score';
    case Accessibility = 'accessibility';
    case Lighthouse = 'lighthouse';
    case Screenshot = 'screenshot';
}
```

- [ ] **Step 3: Add model casts**

```php
// Prospect.php
'audit_status' => AuditStatus::class,

// AuditJob.php
'status' => AuditJobStatus::class,
'job_type' => AuditJobType::class,
```

- [ ] **Step 4: Run tests**

```bash
php artisan test --filter=ProspectAuditCastsTest
php artisan test --filter=AuditJob
```

- [ ] **Step 5: Commit**

```bash
git add app/Enums/Audit*.php app/Models/Prospect.php app/Models/AuditJob.php tests/Unit/Enums/
git commit -m "$(cat <<'EOF'
Add audit pipeline enums with Prospect and AuditJob casts.
EOF
)"
```

---

### Task 7: Wire core enums into services, jobs, and queries (PR 7, Wave 2)

**Files to modify (grep-driven — replace string literals with enum cases):**

```bash
rg "audit_status|'pending'|'complete'|'failed'|'skipped'" app --glob '*.php' -l
rg "'discovering'|'auditing'|status.*complete|status.*failed" app --glob '*.php' -l
rg "'gbp_score'|'accessibility'|'lighthouse'|'screenshot'" app --glob '*.php' -l
```

Primary targets:
- `app/Services/SearchStatusService.php`
- `app/Services/ProgressFlowService.php`
- `app/Services/ProspectAuditService.php`
- `app/Jobs/{AuditSiteJob,CombineScoresJob,DirectUrlScanJob,ScrapeProspectsJob,ScorePlaceJob,DetectCmsJob,CaptureScreenshotJob}.php`
- `app/Queries/{StuckSiteAuditQuery,FailedSiteAuditQuery,FailedScreenshotQuery,IncompleteAuditQuery}.php`
- `app/Support/{RepairAuditScope,StaleAuditJobCloser}.php`
- `app/Http/Controllers/SearchController.php`, `ProspectController.php`, `NicheScanSampleController.php`
- `app/Http/Resources/SearchProspectResource.php`, `ProspectListResource.php`
- `app/Services/Mcp/McpSearchService.php`, `McpSingleSiteAuditService.php`

- [ ] **Step 1: Write SearchStatusService unit test**

```php
// tests/Unit/SearchStatusServiceTest.php
public function test_marks_search_auditing_when_prospects_pending(): void
{
    $search = Search::factory()->create([
        'status' => SearchStatus::Pending,
        'total_found' => 1,
    ]);
    Prospect::factory()->create([
        'search_id' => $search->id,
        'audit_status' => AuditStatus::Pending,
    ]);

    app(SearchStatusService::class)->refresh($search->fresh());

    $this->assertSame(SearchStatus::Auditing, $search->fresh()->status);
}
```

- [ ] **Step 2: Update SearchStatusService**

```php
use App\Enums\AuditStatus;
use App\Enums\SearchStatus;

// Replace string comparisons:
if (! $search || $search->status->isTerminal()) {
    return;
}

$pendingAudits = (int) ($statusCounts[AuditStatus::Pending->value] ?? 0);

if ($pendingAudits > 0) {
    if ($search->status !== SearchStatus::Auditing) {
        $search->update(['status' => SearchStatus::Auditing]);
    }
    return;
}

$search->update(['status' => SearchStatus::Complete]);
```

Note: `groupBy('audit_status')` pluck keys remain strings from DB — index with `AuditStatus::Pending->value`.

- [ ] **Step 3: Update jobs — example AuditSiteJob**

```php
if ($prospect->audit_status !== AuditStatus::Pending) {
    return;
}

$auditJob = AuditJob::create([
    'job_type' => AuditJobType::Accessibility,
    'status' => AuditJobStatus::Running,
    // ...
]);

$prospect->update(['audit_status' => AuditStatus::Failed]);
```

- [ ] **Step 4: Update repair queries**

Replace `'pending'`, `'failed'`, `'screenshot'`, `'accessibility'` string literals with enum `->value` in SQL where raw strings required, or enum cases in PHP comparisons.

- [ ] **Step 5: Run targeted tests**

```bash
php artisan test --filter=SearchStatusServiceTest
php artisan test --filter=ProgressFlowServiceTest
php artisan test --filter=RepairAuditsCommandTest
php artisan test --filter=StuckSiteAuditQueryTest
php artisan test --filter=FailedSiteAuditQueryTest
php artisan test --filter=ProspectReauditTest
php artisan test --filter=ScrapeProspectsJobTest
```

- [ ] **Step 6: Grep for remaining core enum strings in app/**

```bash
rg "audit_status.*'pending'|status.*'auditing'|job_type.*'screenshot'" app --glob '*.php'
```

Expected: no matches (or only in comments).

- [ ] **Step 7: Full suite + commit**

```bash
php artisan test
git commit -am "$(cat <<'EOF'
Wire search and audit enums through services, jobs, and queries.
EOF
)"
```

---

### Task 8: Secondary enums — niche, scoring, website discovery (PR 8, Wave 3)

**Files:**
- Create: `app/Enums/NicheScanStatus.php`, `DominantAngle.php`, `PitchAngle.php`, `WebsiteUrlSource.php`, `WebsiteDiscoveryConfidence.php`
- Modify: `app/Models/NicheScan.php`, `Prospect.php` (additional casts), `OutreachEmail.php`
- Modify: `app/Jobs/ScanNicheJob.php`, `app/Services/GbpScoringService.php`, `WebsiteDiscoveryService.php`, `ScorePlaceJob.php`

- [ ] **Step 1: Create enums**

```php
enum NicheScanStatus: string { case Pending = 'pending'; case Complete = 'complete'; case Failed = 'failed'; }
enum DominantAngle: string { case Gbp = 'gbp'; case Accessibility = 'accessibility'; case Both = 'both'; }
enum PitchAngle: string { case Gbp = 'gbp'; case Accessibility = 'accessibility'; case Combined = 'combined'; }
enum WebsiteUrlSource: string { case Gbp = 'gbp'; case GoogleCse = 'google_cse'; case Brave = 'brave'; case Operator = 'operator'; }
enum WebsiteDiscoveryConfidence: string { case High = 'high'; case Medium = 'medium'; case Low = 'low'; }
```

- [ ] **Step 2: Add model casts**

```php
// NicheScan.php
'status' => NicheScanStatus::class,

// Prospect.php (add to existing casts)
'dominant_angle' => DominantAngle::class,
'website_url_source' => WebsiteUrlSource::class,
'website_discovery_confidence' => WebsiteDiscoveryConfidence::class,

// OutreachEmail.php
'pitch_angle' => PitchAngle::class,
```

- [ ] **Step 3: Replace string literals in services/jobs**

```bash
rg "'gbp_only'|'low_results'|website_url_source.*'gbp'|dominant_angle" app --glob '*.php' -l
```

Key files: `ScanNicheJob.php`, `GbpScoringService.php`, `WebsiteDiscoveryService.php`, `ScorePlaceJob.php`, `NicheExclusionService.php`, `OutreachEmailGeneratorService.php`.

- [ ] **Step 4: Run tests**

```bash
php artisan test --filter=ScanNicheJob
php artisan test --filter=GbpScoringServiceTest
php artisan test --filter=WebsiteDiscoveryServiceTest
php artisan test --filter=ScorePlaceJobWebsiteDiscoveryTest
php artisan test --filter=ScanNichesCommandTest
```

- [ ] **Step 5: Commit**

```bash
git commit -am "$(cat <<'EOF'
Add secondary domain enums for niche scans, scoring, and website discovery.
EOF
)"
```

---

### Task 9: Ignore reason enums (PR 9, Wave 3)

**Files:**
- Create: `app/Enums/IgnoredProspectReason.php`, `IgnoredNicheReason.php`
- Modify: `app/Models/IgnoredProspect.php`, `IgnoredNiche.php`
- Modify: `app/Services/ProspectExclusionService.php`, `NicheExclusionService.php`
- Modify: `app/Http/Requests/StoreIgnoredProspectRequest.php`, `FilterIgnoredProspectsRequest.php`

- [ ] **Step 1: Create enums and remove model constants**

```php
enum IgnoredProspectReason: string
{
    case Acquired = 'acquired';
    case Cold = 'cold';
    case OutreachFailed = 'outreach_failed';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Acquired => 'Company acquired',
            self::Cold => 'Cold lead',
            self::OutreachFailed => 'Outreach did not work',
            self::Other => 'Other',
        };
    }
}

enum IgnoredNicheReason: string
{
    case Manual = 'manual';
    case LowResults = 'low_results';
}
```

- [ ] **Step 2: Update IgnoredProspect**

```php
// casts
'reason' => IgnoredProspectReason::class,

// label() becomes:
return $this->reason->label();
```

Delete `REASON_*` constants. Update all references:

```bash
rg 'IgnoredProspect::REASON_|IgnoredNiche::REASON_' app tests
```

Replace with enum cases, e.g. `IgnoredProspectReason::Acquired`.

- [ ] **Step 3: Run tests**

```bash
php artisan test --filter=ProspectIgnoreTest
php artisan test --filter=NicheExclusionServiceTest
php artisan test --filter=IgnoredProspect
```

- [ ] **Step 4: Commit**

```bash
git commit -am "$(cat <<'EOF'
Replace ignore reason constants with backed enums.
EOF
)"
```

---

### Task 10: Form Request Rule::enum() and frontend pass (PR 10, Wave 4)

**Files:**
- Modify: `app/Http/Requests/StoreSearchRequest.php`, `FilterProspectListRequest.php`, `GenerateOutreachEmailRequest.php`, `StoreIgnoredProspectRequest.php`, `FilterIgnoredProspectsRequest.php`
- Create: `app/Enums/PitchAngleOption.php` (includes `Auto`)
- Audit: `resources/js/Pages/Prospect/Show.jsx` (website_discovery_confidence comparisons)

- [ ] **Step 1: Create PitchAngleOption enum**

```php
enum PitchAngleOption: string
{
    case Auto = 'auto';
    case Gbp = 'gbp';
    case Accessibility = 'accessibility';
    case Combined = 'combined';
}
```

- [ ] **Step 2: Update Form Requests**

```php
use Illuminate\Validation\Rule;
use App\Enums\ScanType;

// StoreSearchRequest.php
'scan_type' => ['required', Rule::enum(ScanType::class)],

// FilterProspectListRequest.php
'scan_type' => ['nullable', Rule::enum(ScanType::class)],
'dominant_angle' => ['nullable', Rule::enum(DominantAngle::class)],

// GenerateOutreachEmailRequest.php
'pitch_angle' => ['required', Rule::enum(PitchAngleOption::class)],

// StoreIgnoredProspectRequest.php + FilterIgnoredProspectsRequest.php
'reason' => ['required', Rule::enum(IgnoredProspectReason::class)],
// (nullable for filter)
```

Do **not** convert OAuth or booking Form Request `Rule::in()` fields — those are protocol constraints, not domain enums.

- [ ] **Step 3: Grep app/ for remaining in-scope string literals**

```bash
rg "'gbp_only'|'pending'|'acquired'|'manual'" app --glob '*.php' | grep -v vendor
```

Replace any remaining matches with enum cases.

- [ ] **Step 4: Frontend string audit**

Backed enums serialise as strings in Inertia — frontend should already work. Verify and add comment only if needed:

```bash
rg "audit_status|scan_type|pitch_angle" resources/js --glob '*.jsx'
```

Ensure comparisons use the same string values (`'pending'`, `'high'`, etc.). No JS enum import required.

- [ ] **Step 5: Full verification**

```bash
php artisan test
./vendor/bin/pint --test
rg 'AnthropicService' app tests
rg "SearchQueue::apply|NicheQueue::apply|AuditingQueue::apply" app
```

Expected: all tests pass, Pint clean, no AnthropicService, no `apply()` calls.

- [ ] **Step 6: Commit**

```bash
git commit -am "$(cat <<'EOF'
Align Form Request validation with domain enums and complete L13 modernisation pass.
EOF
)"
```

---

## Programme completion checklist

- [ ] `OpenRouterService` is the only LLM transport class in `app/`
- [ ] 14 enums in `app/Enums/`
- [ ] All 10 scanner jobs use `Queue::route()` (no constructor `apply()`)
- [ ] Job attributes replace `$tries` / `$timeout` properties
- [ ] `./vendor/bin/pint --test` passes
- [ ] `php artisan test` — 431+ passed, 2 skipped
- [ ] Staging verified with hybrid queue connections

---

## Spec coverage self-review

| Spec requirement | Task |
|------------------|------|
| OpenRouter rename | Task 1 |
| Queue::route() | Task 2 |
| Job attributes | Task 3 |
| Pint cleanup | Task 4 |
| Core enums (6) | Tasks 5–7 |
| Secondary enums (6) | Tasks 8–9 |
| Rule::enum() validation | Task 10 |
| No AI SDK / no JSON:API | Out of scope (documented in spec) |
| No DB migrations | All tasks use model casts only |
| Controller #[Authorize] | Deferred per spec — not in plan |

No placeholders. All tasks have concrete paths and code patterns.
