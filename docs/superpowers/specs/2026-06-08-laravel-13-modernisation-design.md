# Laravel 13 Modernisation Design

**Date:** 2026-06-08  
**Status:** Approved for implementation  
**Baseline:** Laravel 13.11, PHP 8.3, 433 tests (431 pass, 2 skipped)  
**Prior programme:** [2026-06-05 lean audit refactor](../audits/2026-06-05-backend-refactor-backlog.md) — complete; this spec addresses Laravel 13 idioms not covered by that work.

## Summary

Bring the nthdesigns Prospect Scanner backend in line with Laravel 13 best practices through a phased, four-wave programme. Focus is **framework-native patterns** (backed enums, job attributes, `Queue::route()`, honest service naming) — not performance refactors or Laravel AI SDK migration.

**Key decisions:**

- Keep **OpenRouter** for outreach LLM calls (frontier model switching via `OPENROUTER_MODEL`)
- Rename **`AnthropicService` → `OpenRouterService`** so naming matches transport, not model vendor
- **Comprehensive enum sweep** across domain status/type fields
- **No DB migrations** — Postgres `enum`/`string` columns unchanged; PHP enums cast at model layer
- **No JSON:API resources** — Inertia app keeps static `*Resource::format()` helpers

## Programme charter

### In scope

| Area | Change |
|------|--------|
| Service naming | `AnthropicService` → `OpenRouterService` |
| Domain enums | 14 backed enums in `app/Enums/` |
| Model casts | Enum casts on all in-scope columns |
| Validation | `Rule::enum()` in Form Requests |
| Queue routing | `Queue::route()` in `AppServiceProvider::boot()` |
| Job config | `#[Tries]`, `#[Timeout]`, `#[WithoutRelations]`, `#[DeleteWhenMissingModels]` |
| Code style | Pint fix (3 remaining files) |

### Out of scope

- Laravel AI SDK (OpenRouter retained)
- JSON:API resource migration
- Performance work (unbounded queries, worker `sleep()` removal)
- Large service decomposition (`BrowserServiceClient`, etc.)
- DB column type changes
- Frontend redesign (enum values serialize as strings; update comparisons only where needed)

### Success criteria

- Zero raw string literals for in-scope domain values in `app/` (use enum cases or `->value`)
- All scanner jobs routed via `Queue::route()`; job constructors no longer call `*Queue::apply()`
- Job retry/timeout expressed via attributes where currently using `$tries` / `$timeout` properties
- `OpenRouterService` is the sole LLM transport class; no `AnthropicService` references remain
- Full test suite green; Laravel Cloud queue env vars unchanged

---

## Enum inventory

All enums are `string`-backed, values match existing DB/check constraints exactly.

### Wave 2 — Core pipeline

| Enum | Backed values | Primary consumers |
|------|---------------|-------------------|
| `SearchStatus` | `pending`, `discovering`, `auditing`, `complete`, `failed` | `Search`, `SearchStatusService`, `ProgressFlowService`, `ScrapeProspectsJob` |
| `ScanType` | `gbp_only`, `accessibility_only`, `combined` | `Search`, scoring, Form Requests |
| `SearchSource` | `discovery`, `direct_url` | `Search::isDirectUrl()` |
| `AuditStatus` | `pending`, `complete`, `failed`, `skipped` | `Prospect`, audit jobs, repair queries |
| `AuditJobStatus` | `pending`, `running`, `complete`, `failed` | `AuditJob`, repair queries |
| `AuditJobType` | `gbp_score`, `accessibility`, `lighthouse`, `screenshot` | `AuditJob`, `StaleAuditJobCloser` |

### Wave 3 — Secondary domain

| Enum | Backed values | Primary consumers |
|------|---------------|-------------------|
| `NicheScanStatus` | `pending`, `complete`, `failed` | `NicheScan`, `ScanNicheJob` |
| `DominantAngle` | `gbp`, `accessibility`, `both` | `Prospect`, `GbpScoringService` |
| `PitchAngle` | `gbp`, `accessibility`, `combined` | `OutreachEmail` |
| `PitchAngleOption` | `auto`, `gbp`, `accessibility`, `combined` | `GenerateOutreachEmailRequest` (`auto` is request-only) |
| `WebsiteUrlSource` | `gbp`, `google_cse`, `brave`, `operator` | `Prospect`, `WebsiteDiscoveryService` |
| `WebsiteDiscoveryConfidence` | `high`, `medium`, `low` | `Prospect` (when set) |
| `IgnoredProspectReason` | `acquired`, `cold`, `outreach_failed`, `other` | Replaces `IgnoredProspect::REASON_*` constants |
| `IgnoredNicheReason` | `manual`, `low_results` | `IgnoredNiche`, `NicheExclusionService` |

### Casting pattern

```php
// app/Models/Prospect.php
protected function casts(): array
{
    return [
        'audit_status' => AuditStatus::class,
        'dominant_angle' => DominantAngle::class,
        'website_url_source' => WebsiteUrlSource::class,
        'website_discovery_confidence' => WebsiteDiscoveryConfidence::class,
    ];
}
```

### Comparison and assignment

```php
// Before
if ($prospect->audit_status === 'pending') { ... }
$prospect->update(['audit_status' => 'complete']);

// After
if ($prospect->audit_status === AuditStatus::Pending) { ... }
$prospect->update(['audit_status' => AuditStatus::Complete]);
```

### Validation

```php
'scan_type' => ['required', Rule::enum(ScanType::class)],
```

### Serialization

Backed enums serialize to their string value in Inertia props and JSON. Frontend components comparing `audit_status === 'pending'` continue to work during Waves 2–3; Wave 4 updates any strict equality in `resources/js/` to use the same string values (no enum import needed client-side).

### Optional enum helpers

Add `isPending()`, `isTerminal()` etc. only where they reduce repeated `match` blocks (e.g. `AuditStatus`, `SearchStatus`). Avoid boilerplate on every enum.

---

## Queue routing (Wave 1)

Replace per-job `SearchQueue::apply($this)` / `NicheQueue::apply($this)` / `AuditingQueue::apply($this)` constructor calls with centralised routing in `AppServiceProvider::boot()`:

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
use Illuminate\Support\Facades\Queue;

public function boot(): void
{
    // ...existing boot logic...

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
}
```

### Keep `*Queue` support classes

`SearchQueue`, `NicheQueue`, and `AuditingQueue` remain as named constants + `connection()` helpers. `AuditingQueuePresence` and tests referencing `AuditingQueue::NAME` / `::connection()` are unchanged. Remove only `apply()` calls from job constructors and the `apply()` method itself once all jobs use `Queue::route()`.

`NicheQueue::dispatch()` and `SearchQueue::dispatch()` in controllers/commands may remain — they wrap `dispatch()` with routing that `Queue::route()` now provides automatically. Optionally simplify to plain `ScanNicheJob::dispatch()` in Wave 1.

### Jobs outside scanner queues

`SendReportBookingConfirmationJob` uses the default queue connection. Add explicit `Queue::route()` only if it should run on `searches`; otherwise leave on default.

### Deployment safety

No `.env` changes. `SEARCH_QUEUE_CONNECTION`, `NICHE_QUEUE_CONNECTION`, and `AUDITING_QUEUE_CONNECTION` continue to drive routing via `config('scanner.*')`.

---

## Job attributes (Wave 1)

Migrate from public properties to Laravel 13 attributes per [queue docs](https://laravel.com/docs/13.x/queues):

| Job | Attributes |
|-----|------------|
| `AuditSiteJob` | `#[Tries(2)]`, `#[Timeout(240)]`, `#[WithoutRelations]` on `$prospect` |
| `CaptureScreenshotJob` | `#[Tries(3)]`, `#[Timeout(180)]`, `#[WithoutRelations]` on `$report` |
| `CombineScoresJob` | `#[Tries(2)]`, `#[WithoutRelations]` |
| `DirectUrlScanJob` | `#[WithoutRelations]` |
| `GenerateOutreachEmailJob` | `#[Tries(2)]`, `#[Timeout(90)]`, `#[WithoutRelations]` |
| `GenerateProspectReportJob` | `#[Tries(2)]`, `#[WithoutRelations]` |
| `ScanNicheJob` | `#[WithoutRelations]` where models passed |
| `ScorePlaceJob` | `#[WithoutRelations]` |
| `ScrapeProspectsJob` | `#[WithoutRelations]` |
| `DetectCmsJob` | `#[WithoutRelations]` |
| `SendReportBookingConfirmationJob` | `#[Tries(3)]`, `#[DeleteWhenMissingModels]` (uses `$bookingId` not model — no `WithoutRelations`) |

Keep `$backoff` arrays as properties where non-trivial (`CaptureScreenshotJob`: `[60, 120]`; `AuditSiteJob`: `60`) until `#[Backoff]` is confirmed in installed framework version.

Remove empty constructors that only called `*Queue::apply($this)`.

---

## OpenRouter service rename (Wave 1)

| Before | After |
|--------|-------|
| `app/Services/AnthropicService.php` | `app/Services/OpenRouterService.php` |
| `tests/Unit/AnthropicServiceTest.php` | `tests/Unit/OpenRouterServiceTest.php` |
| `OutreachEmailGeneratorService::$anthropic` | `$openRouter` |

Behaviour unchanged: reads `config('services.openrouter.*')`, posts to OpenRouter `/chat/completions`, returns `model_used` from response.

Model selection remains env-driven:

```env
OPENROUTER_MODEL=anthropic/claude-sonnet-4
```

Update stale doc references in `docs/concept/` and `docs/design/` only when touched by other work — not a Wave 1 requirement.

---

## Controller attributes (Wave 1 — optional, low priority)

Laravel 13 supports `#[Authorize]` on controller methods. Adoption is **optional in Wave 1** because existing `$this->authorize()` calls are idiomatic and grep-clean.

If adopted later, prefer on single-action controllers (e.g. `ExportController::store`) rather than bulk replacement across `ProspectController`.

**Defer `#[Middleware]`** — middleware is already declared in `routes/web.php` and `bootstrap/app.php`.

---

## Testing strategy

| Wave | Test focus |
|------|------------|
| W1 | `OpenRouterServiceTest` rename; `AuditingQueueTest` / job dispatch tests assert queue via `Queue::route()`; Pint green |
| W2 | Enum cast round-trips on models; `SearchStatusServiceTest`; repair query tests with enum cases |
| W3 | Ignored prospect/niche reason enums; outreach pitch angle enums |
| W4 | Frontend string comparisons audit; full `php artisan test` |

Run `php artisan test` after each PR. No production deployment until Wave 1 queue routing is verified on staging with hybrid `database` + `cloud` connections.

---

## PR schedule

| PR | Wave | Title | Est. effort |
|----|------|-------|-------------|
| 1 | W1 | Rename AnthropicService → OpenRouterService | 1h |
| 2 | W1 | Queue::route() + remove job constructor queue wiring | 2h |
| 3 | W1 | Job PHP attributes (Tries, Timeout, WithoutRelations) | 2h |
| 4 | W1 | Pint cleanup (3 files) | 0.5h |
| 5 | W2 | Core enums: SearchStatus, ScanType, SearchSource + model casts | 3h |
| 6 | W2 | Core enums: AuditStatus, AuditJobStatus, AuditJobType | 4h |
| 7 | W2 | Wire enums into SearchStatusService, ProgressFlowService, repair queries | 4h |
| 8 | W3 | Secondary enums: NicheScan, DominantAngle, PitchAngle, WebsiteUrlSource | 3h |
| 9 | W3 | IgnoredProspectReason, IgnoredNicheReason + remove model constants | 2h |
| 10 | W4 | Rule::enum() in all Form Requests; JS string comparison pass | 3h |

**Total estimate:** ~24 hours across 10 reviewable PRs.

---

## Risks and mitigations

| Risk | Mitigation |
|------|------------|
| Enum cast breaks raw string writes in jobs | Grep for string assignments before each wave; use enum cases in edits |
| `Queue::route()` config read at boot time | `config('scanner.*')` is env-driven but stable per deploy; same as current `*Queue::connection()` |
| Inertia props change shape | Backed enums serialize as strings — no shape change |
| OpenRouter rename breaks docs/tests | Mechanical rename with IDE; run full test suite |
| Partial enum migration confusion | Complete one subsystem per PR (W2=search/audit, W3=outreach/niche) |

---

## References

- [Laravel 13 queue routing](https://laravel.com/docs/13.x/queues#queue-routing)
- [Laravel 13 job attributes](https://laravel.com/docs/13.x/queues#specifying-max-job-attempts-timeout-values)
- [2026-06-05 backend refactor backlog](../audits/2026-06-05-backend-refactor-backlog.md) — deferred optional debt not in this programme
- [Laravel Cloud deployment](../../deployment/laravel-cloud.md) — hybrid queue config
