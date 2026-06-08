# Deferred Backlog Follow-up Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close six deferred backlog items from post–Laravel 13 modernisation hygiene — queue wrapper cleanup, OpenRouter doc alignment, CMS detection deduplication, outreach/MCP performance bounds, and three large-service decompositions — as independently mergeable PRs with no product behaviour change unless explicitly noted.

**Architecture:** Seven small-to-medium PRs ordered quick-wins → behavioural fix (CMS) → performance → structural decomposition. Each PR is self-contained, test-gated, and references REF IDs from `docs/superpowers/audits/2026-06-05-backend-refactor-backlog.md`. Decomposition PRs keep public class names (`BrowserServiceClient`, `GooglePlacesService`, `NichesBootstrapSteps`) as thin facades delegating to focused collaborators under `app/Services/{Browser,GooglePlaces,Niches}/`.

**Tech Stack:** Laravel 13.11, PHP 8.3, PHPUnit 12, Inertia (Outreach index only if pagination UI added), Postgres.

**Source backlog:** June 2026 audit deferred optional debt + L13 spec § optional simplifications.

---

## PR schedule

| PR | Theme | REF / source | Est. | Risk |
|----|-------|--------------|------|------|
| **#1** | Queue wrapper cleanup | L13 spec § optional | 0.5h | Low |
| **#2** | OpenRouter doc hygiene | L13 spec § docs | 1h | None |
| **#3** | CMS single path | REF-S3-07 | 2–3h | Medium |
| **#4** | Outreach bounded loads | REF-S6-06 + unbounded `->get()` | 3–4h | Low |
| **#5** | MCP watch loop bounds | REF-S7-05 | 2–3h | Low |
| **#6** | `BrowserServiceClient` decomposition | S10 file-size | 3–4h | Low |
| **#7** | `GooglePlacesService` decomposition | REF-S1-08 adjacent | 3–4h | Low |
| **#8** | `NichesBootstrapSteps` decomposition | S2 bootstrap | 2–3h | Low |

**Recommended order:** #1 → #2 → #3 → #4 → #5 → #6–#8 (any order; do not batch decompositions).

---

## File map

| Path | Responsibility |
|------|----------------|
| `app/Support/SearchQueue.php` | Queue name + connection constants; remove dead `dispatch()` / `chain()` |
| `app/Support/NicheQueue.php` | Same |
| `app/Console/Commands/ScanNichesCommand.php` | `ScanNicheJob::dispatch()` |
| `app/Http/Controllers/NicheScanSampleController.php` | `ScanNicheJob::dispatch()` |
| `app/Jobs/AuditSiteJob.php` | Prefer `cms` from audit payload; no redundant HTTP |
| `app/Services/Outreach/OutreachQueueLoader.php` | **Create** — bounded selection + latest-email query |
| `app/Http/Controllers/OutreachController.php` | Delegate to loader |
| `app/Services/Mcp/McpProgressStreamHandler.php` | Finer-grained poll loop + early abort |
| `docs/mcp-integration-guide.md` | Worker occupancy guidance |
| `app/Services/Browser/BrowserHttpTransport.php` | **Create** — shared HTTP to Fly browser service |
| `app/Services/Browser/BrowserAuditGateway.php` | **Create** — `fetchAudit` + failed-response parsing |
| `app/Services/Browser/BrowserCmsGateway.php` | **Create** — `fetchCmsDetection` |
| `app/Services/Browser/BrowserScreenshotGateway.php` | **Create** — `captureDesktop` |
| `app/Services/BrowserServiceClient.php` | Thin facade over Browser/* gateways |
| `app/Services/GooglePlaces/PlacesDetailsClient.php` | **Create** — `getPlaceDetails` + cache |
| `app/Services/GooglePlaces/PlacesNicheRankClient.php` | **Create** — `getTopRankedInNiche` |
| `app/Services/GooglePlaces/PlacesWebsiteLookupClient.php` | **Create** — `findByWebsiteUrl` |
| `app/Services/GooglePlacesService.php` | Thin facade |
| `app/Services/Niches/NichesTaxonomyParser.php` | **Create** — taxonomy fetch + allow/block lists |
| `app/Services/NichesBootstrapSteps.php` | Orchestrator only |

---

## Verification baseline

```bash
cd /Users/rosstweedie/Sites/nthdesigns-scanner
php artisan test
./vendor/bin/pint --test
```

Expected before any task: **433 passed**, 2 skipped; Pint clean.

After each PR: full suite + targeted filters listed per task.

---

### Task 1: Remove dead queue `dispatch()` / `chain()` wrappers (PR #1)

**Context:** `Queue::route()` in `ScannerConfig::registerQueueRoutes()` handles job routing. `NicheQueue::dispatch()` is a no-op wrapper around `dispatch($job)`. `SearchQueue::dispatch()` has **zero** call sites. `::chain()` is unused on both classes.

**Files:**
- Modify: `app/Support/SearchQueue.php`
- Modify: `app/Support/NicheQueue.php`
- Modify: `app/Console/Commands/ScanNichesCommand.php`
- Modify: `app/Http/Controllers/NicheScanSampleController.php`
- Test: `tests/Feature/NicheScanSampleControllerTest.php`, `tests/Feature/ScanNichesCommandTest.php`

- [ ] **Step 1: Trim queue support classes**

Remove `dispatch()` and `chain()` from both classes. Keep `NAME`, `connection()`:

```php
// app/Support/NicheQueue.php — final shape
final class NicheQueue
{
    public const NAME = 'niches';

    public static function connection(): string
    {
        return (string) config('scanner.niche_queue_connection', config('queue.default'));
    }
}
```

Apply the same trim to `SearchQueue.php` (keep `NAME = 'searches'`).

- [ ] **Step 2: Replace call sites**

```php
// app/Console/Commands/ScanNichesCommand.php — remove NicheQueue import
ScanNicheJob::dispatch(
    niche: $niche['label'],
    // ... unchanged args
);
```

```php
// app/Http/Controllers/NicheScanSampleController.php — remove NicheQueue import
ScanNicheJob::dispatch(
    niche: $nicheScan->niche,
    // ... unchanged args
);
```

- [ ] **Step 3: Run tests**

```bash
php artisan test --filter='NicheScanSampleControllerTest|ScanNichesCommandTest|AuditingQueueTest|GenerateOutreachEmailJobTest'
```

Expected: all PASS; jobs still resolve to `niches` / `searches` queues via `Queue::route()`.

- [ ] **Step 4: Grep guard**

```bash
rg 'NicheQueue::dispatch|SearchQueue::dispatch|::chain\(' app tests
```

Expected: no matches.

- [ ] **Step 5: Commit**

```bash
git add app/Support/SearchQueue.php app/Support/NicheQueue.php \
  app/Console/Commands/ScanNichesCommand.php app/Http/Controllers/NicheScanSampleController.php
git commit -m "refactor: drop redundant queue dispatch wrappers after Queue::route()"
```

---

### Task 2: OpenRouter doc hygiene (PR #2)

**Context:** `AnthropicService` was renamed to `OpenRouterService` in PR #33. Stale references remain in concept/design docs and historical plans. Operator-facing copy should say **OpenRouter** (transport) while noting models may be Anthropic via `OPENROUTER_MODEL`.

**Files:**
- Modify: `docs/concept/claude-design-prompt.md`
- Modify: `docs/concept/nthdesigns-prospect-scanner-plan.md`
- Modify: `docs/concept/gbp-prospect-tool-build-plan.md`
- Modify: `docs/design/uploads/claude-design-prompt.md`
- Modify: `docs/design/design_handoff_prospect_scanner/README.md`
- Modify: `docs/design/2026-05-26-operator-ui/prototype.html` (label only)
- Modify: `docs/design/Canvas Overview.html` (label only)
- Modify: `docs/design/design_handoff_prospect_scanner/Canvas Overview.html` (label only)
- Modify: `docs/design/uploads/2026-05-26-operator-ui-design.md`
- Modify: `docs/superpowers/specs/2026-05-26-operator-ui-design.md`
- Modify: `docs/deployment/laravel-cloud.md` (diagram label — keep "OpenRouter / Anthropic models" clarity)
- Modify: `docs/superpowers/audits/2026-06-05-backend-refactor-backlog.md` (historical file paths only)

**Do not edit:** `docs/superpowers/plans/2026-06-08-laravel-13-modernisation.md` (historical task log).

- [ ] **Step 1: Replace service naming**

| Find | Replace with |
|------|----------------|
| `AnthropicService` | `OpenRouterService` |
| `Anthropic API` (health/settings context) | `OpenRouter API` |
| `Anthropic (Claude)` (settings health row) | `OpenRouter (LLM)` |
| `via Laravel AI SDK` for outreach LLM | `via OpenRouterService` (this project does not use Laravel AI SDK) |

Leave `OPENROUTER_MODEL=anthropic/...` examples unchanged.

- [ ] **Step 2: Verify no stale service references in active docs**

```bash
rg 'AnthropicService' docs/concept docs/design docs/deployment docs/mcp-integration-guide.md
```

Expected: no matches (audit backlog historical mentions may remain in findings tables — update path references only).

- [ ] **Step 3: Commit**

```bash
git add docs/
git commit -m "docs: align concept and design copy with OpenRouterService naming"
```

---

### Task 3: CMS single detection path (PR #3)

**Context:** `scripts/audit.js` already runs `detectCms()` during full audits and returns `cms` in the JSON payload. `AuditSiteJob` ignores that field and calls `CmsDetectionRunnerService::run()` again — a duplicate Fly HTTP round-trip (or second Playwright invocation). `DetectCmsJob` remains correct for `gbp_only` / no-a11y paths. REF-S3-07.

**Files:**
- Create: `app/Support/CmsDetectionPayload.php`
- Modify: `app/Jobs/AuditSiteJob.php`
- Create: `tests/Unit/CmsDetectionPayloadTest.php`
- Modify: `tests/Feature/CmsDetectionIntegrationTest.php` (or add `AuditSiteJobCmsTest.php`)

- [ ] **Step 1: Write failing unit test for payload normalizer**

```php
// tests/Unit/CmsDetectionPayloadTest.php
public function test_from_audit_payload_returns_cms_when_valid(): void
{
    $payload = ['cms' => ['platform' => 'wordpress', 'confidence' => 'high', 'signals' => []]];
    $result = CmsDetectionPayload::fromAuditPayload($payload);
    $this->assertSame('wordpress', $result['platform']);
}

public function test_from_audit_payload_returns_null_when_missing(): void
{
    $this->assertNull(CmsDetectionPayload::fromAuditPayload(['violations' => []]));
}
```

- [ ] **Step 2: Run test — expect FAIL**

```bash
php artisan test --filter=CmsDetectionPayloadTest
```

- [ ] **Step 3: Implement normalizer**

```php
// app/Support/CmsDetectionPayload.php
final class CmsDetectionPayload
{
    /**
     * @param  array<string, mixed>  $auditPayload
     * @return array<string, mixed>|null
     */
    public static function fromAuditPayload(array $auditPayload): ?array
    {
        $cms = $auditPayload['cms'] ?? null;

        if (! is_array($cms) || ! isset($cms['platform'])) {
            return null;
        }

        return $cms;
    }
}
```

- [ ] **Step 4: Update AuditSiteJob — prefer inline cms**

Replace the unconditional `cmsRunner->run()` block (lines ~100–108) with:

```php
$cms = CmsDetectionPayload::fromAuditPayload($payload);

if ($cms === null) {
    try {
        $cms = $cmsRunner->run($prospect->website_url);
    } catch (\Throwable $e) {
        Log::warning('AuditSiteJob CMS detection failed', [
            'prospect_id' => $prospect->id,
            'url' => $prospect->website_url,
            'error' => $e->getMessage(),
        ]);
        $cms = null;
    }
}

if ($cms !== null) {
    $updates['cms_detection'] = $cms;
}
```

- [ ] **Step 5: Feature test — audit path does not call cms runner when payload has cms**

```php
// tests/Feature/AuditSiteJobCmsTest.php
public function test_uses_cms_from_audit_payload_without_second_runner_call(): void
{
    $prospect = Prospect::factory()->pendingAudit()->create(['website_url' => 'https://example.com']);

    $auditRunner = Mockery::mock(AuditRunnerService::class);
    $auditRunner->shouldReceive('shouldSkip')->andReturn(false);
    $auditRunner->shouldReceive('run')->andReturn([
        'violations' => [],
        'cms' => ['platform' => 'wordpress', 'confidence' => 'high', 'signals' => []],
    ]);

    $cmsRunner = Mockery::mock(CmsDetectionRunnerService::class);
    $cmsRunner->shouldNotReceive('run');

    // dispatch handle with mocked deps ...
    $prospect->refresh();
    $this->assertSame('wordpress', $prospect->cms_detection['platform']);
}
```

- [ ] **Step 6: Run CMS + audit tests**

```bash
php artisan test --filter='CmsDetectionPayloadTest|AuditSiteJobCmsTest|CmsDetectionIntegrationTest|DetectCmsJobTest'
php artisan test
```

- [ ] **Step 7: Commit**

```bash
git add app/Support/CmsDetectionPayload.php app/Jobs/AuditSiteJob.php tests/
git commit -m "fix: use CMS from audit payload instead of duplicate detection call"
```

**Out of scope:** Changing `ScorePlaceJob` `DetectCmsJob` dispatch rules (already correct for non-audit paths).

---

### Task 4: Outreach bounded loads (PR #4)

**Context:** `OutreachController::index()` and `generate()` call `->get()` without limit. Large queues load all selections + all user emails. REF-S6-06.

**Approach:** Introduce `OutreachQueueLoader` with (a) configurable max queue size, (b) latest-email-per-prospect SQL instead of loading all `OutreachEmail` rows.

**Files:**
- Create: `app/Services/Outreach/OutreachQueueLoader.php`
- Modify: `app/Http/Controllers/OutreachController.php`
- Modify: `config/scanner.php` — add `outreach_queue_max` (default `200`)
- Modify: `tests/Feature/OutreachIndexTest.php`
- Create: `tests/Unit/Outreach/OutreachQueueLoaderTest.php`

- [ ] **Step 1: Add config**

```php
// config/scanner.php
'outreach_queue_max' => (int) env('OUTREACH_QUEUE_MAX', 200),
```

- [ ] **Step 2: Write failing loader test**

```php
// tests/Unit/Outreach/OutreachQueueLoaderTest.php
public function test_latest_emails_returns_one_per_prospect(): void
{
    $user = User::factory()->create();
    $prospect = Prospect::factory()->create();
    OutreachEmail::factory()->create(['user_id' => $user->id, 'prospect_id' => $prospect->id, 'created_at' => now()->subDay()]);
    $latest = OutreachEmail::factory()->create(['user_id' => $user->id, 'prospect_id' => $prospect->id]);

    $loader = app(OutreachQueueLoader::class);
    $map = $loader->latestEmailsByProspect($user, collect([$prospect->id]));

    $this->assertCount(1, $map[$prospect->id]);
    $this->assertSame($latest->id, $map[$prospect->id][0]['id']); // or compare subject
}
```

- [ ] **Step 3: Implement loader**

```php
// app/Services/Outreach/OutreachQueueLoader.php
namespace App\Services\Outreach;

use App\Http\Resources\OutreachEmailResource;
use App\Models\OutreachEmail;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class OutreachQueueLoader
{
    public function selections(User $user, bool $bookedOnly): EloquentCollection
    {
        $query = $user->outreachSelections()
            ->with(['prospect.search', 'prospect.report.booking', 'prospect.outreachEmails' => fn ($q) => $q->latest()])
            ->orderBy('created_at')
            ->limit((int) config('scanner.outreach_queue_max', 200));

        if ($bookedOnly) {
            $query->whereHas('prospect.report.booking');
        }

        return $query->get();
    }

    /**
     * @param  Collection<int, int>  $prospectIds
     * @return array<int, list<array<string, mixed>>>
     */
    public function latestEmailsByProspect(User $user, Collection $prospectIds): array
    {
        if ($prospectIds->isEmpty()) {
            return [];
        }

        $emails = OutreachEmail::query()
            ->where('user_id', $user->id)
            ->whereIn('prospect_id', $prospectIds)
            ->whereIn('id', function ($sub) use ($user, $prospectIds) {
                $sub->selectRaw('MAX(id)')
                    ->from('outreach_emails')
                    ->where('user_id', $user->id)
                    ->whereIn('prospect_id', $prospectIds)
                    ->groupBy('prospect_id');
            })
            ->get();

        return $emails
            ->groupBy('prospect_id')
            ->map(fn ($group) => $group
                ->map(fn (OutreachEmail $email) => OutreachEmailResource::format($email))
                ->values()
                ->all())
            ->all();
    }
}
```

- [ ] **Step 4: Wire OutreachController**

```php
public function __construct(
    private UserSettingsService $settings,
    private OutreachQueueLoader $queue,
) {}

public function index(Request $request): Response
{
    $user = $request->user();
    $bookedOnly = $request->boolean('booked');
    $selections = $this->queue->selections($user, $bookedOnly);
    $emailsByProspect = $this->queue->latestEmailsByProspect($user, $selections->pluck('prospect_id'));

    // ... unchanged Inertia render
}

public function generate(GenerateOutreachEmailRequest $request): RedirectResponse
{
    // ...
    $selections = $this->queue->selections($request->user(), bookedOnly: false);
    // ... unchanged foreach dispatch
}
```

- [ ] **Step 5: Add queue-truncation flash (optional UX)**

If `$user->outreachSelections()->count() > config('scanner.outreach_queue_max')`, pass `queueTruncated: true` to Inertia so `Index.jsx` can show a one-line warning. **Skip if avoiding frontend change** — backend limit alone is sufficient for v1.

- [ ] **Step 6: Run tests**

```bash
php artisan test --filter='OutreachIndexTest|OutreachGenerateTest|OutreachQueueLoaderTest'
php artisan test
```

- [ ] **Step 7: Commit**

```bash
git add app/Services/Outreach/ app/Http/Controllers/OutreachController.php config/scanner.php tests/
git commit -m "perf: bound outreach queue loads and latest-email query"
```

---

### Task 5: MCP watch loop bounds (PR #5)

**Context:** `McpProgressStreamHandler` holds a PHP worker up to 45s with `sleep(2)` (REF-S7-05). Streamable transport is opt-in; legacy JSON-RPC polling is unaffected.

**Approach (pragmatic, no new infrastructure):**
1. Document worker occupancy cost.
2. Replace coarse `sleep(2)` with sub-second polling + `connection_aborted()` check each iteration.
3. Add test proving watch returns before timeout when `search_complete` flips true.

**Files:**
- Modify: `app/Services/Mcp/McpProgressStreamHandler.php`
- Modify: `docs/mcp-integration-guide.md`
- Modify: `config/scanner.php` — document `mcp_progress_poll_seconds` (existing key)
- Test: extend `tests/Feature/McpScanToolsTest.php`

- [ ] **Step 1: Extract poll interval helper**

```php
// app/Services/Mcp/McpProgressStreamHandler.php
private function waitForNextPoll(): void
{
    $seconds = max(1, (int) config('scanner.mcp_progress_poll_seconds', 2));
    $deadline = microtime(true) + $seconds;

    while (microtime(true) < $deadline) {
        if (connection_aborted()) {
            return;
        }
        usleep(200_000); // 200ms
    }
}
```

Replace `sleep(max(1, ...))` call with `$this->waitForNextPoll()`.

- [ ] **Step 2: Update docs**

Add to `docs/mcp-integration-guide.md` § Worker note:

> Each concurrent streamable `watch_search_progress` holds one app worker for up to 45s. Prefer `get_search_progress_flow` for agent polling unless you need SSE `notifications/progress`. On Laravel Cloud, size app cluster workers for expected concurrent watches (e.g. 2 agents × 1 watch = 2 workers busy).

- [ ] **Step 3: Run MCP tests**

```bash
php artisan test --filter=McpScanToolsTest
php artisan test
```

- [ ] **Step 4: Commit**

```bash
git add app/Services/Mcp/McpProgressStreamHandler.php docs/mcp-integration-guide.md
git commit -m "perf: finer MCP progress polling with early client abort"
```

**Deferred (do not implement in this PR):** Async notifier / Reverb / dedicated watch queue — only if concurrent watch load becomes a production incident.

---

### Task 6: `BrowserServiceClient` decomposition (PR #6)

**Context:** 273-line class mixing audit, CMS, screenshot, health, and HTTP transport. `ViolationScreenshotMaterializer` already extracted. No behaviour change.

**Files:**
- Create: `app/Services/Browser/BrowserHttpTransport.php`
- Create: `app/Services/Browser/BrowserAuditGateway.php`
- Create: `app/Services/Browser/BrowserCmsGateway.php`
- Create: `app/Services/Browser/BrowserScreenshotGateway.php`
- Modify: `app/Services/BrowserServiceClient.php` — delegate only
- Test: existing `tests/Unit/BrowserServiceClientTest.php` (or feature tests referencing client)

- [ ] **Step 1: Extract HTTP transport**

Move `request()`, `baseUrl()`, `endpoint()`, token header logic into `BrowserHttpTransport`. Public methods: `post(string $path, array $body, int $timeout): Response`, `get(...)`, `baseUrl(): string`.

- [ ] **Step 2: Move gateways**

| Gateway | Methods moved from `BrowserServiceClient` |
|---------|-------------------------------------------|
| `BrowserAuditGateway` | `fetchAudit`, `parseAuditPayloadFromFailedResponse`, `unreachableAuditPayload` |
| `BrowserCmsGateway` | `fetchCmsDetection` |
| `BrowserScreenshotGateway` | `captureDesktop`, `parseScreenshotPayloadFromFailedResponse` |

Keep `materializeViolationScreenshots()` delegating to `ViolationScreenshotMaterializer` on the facade.

- [ ] **Step 3: Facade**

```php
class BrowserServiceClient
{
    public function __construct(
        private BrowserAuditGateway $audit,
        private BrowserCmsGateway $cms,
        private BrowserScreenshotGateway $screenshot,
        private BrowserHttpTransport $http,
    ) {}

    public function fetchAudit(string $url): array
    {
        return $this->audit->fetch($url);
    }
    // ... etc
}
```

- [ ] **Step 4: Run tests**

```bash
php artisan test --filter='BrowserService|AuditRunner|CmsDetection|CaptureScreenshot'
php artisan test
```

- [ ] **Step 5: Commit**

```bash
git add app/Services/Browser/ app/Services/BrowserServiceClient.php
git commit -m "refactor: decompose BrowserServiceClient into Browser gateways"
```

---

### Task 7: `GooglePlacesService` decomposition (PR #7)

**Context:** 229-line service; `PlacesTextSearchClient` already extracted for text search. Remaining methods: details+cache, niche rank, website lookup.

**Files:**
- Create: `app/Services/GooglePlaces/PlacesDetailsClient.php`
- Create: `app/Services/GooglePlaces/PlacesNicheRankClient.php`
- Create: `app/Services/GooglePlaces/PlacesWebsiteLookupClient.php`
- Modify: `app/Services/GooglePlacesService.php`
- Test: `tests/Unit/GooglePlacesServiceTest.php`

- [ ] **Step 1: Extract PlacesDetailsClient**

Move `getPlaceDetails()`, `detailsFieldMask()`, `detailsCacheKey()`, `placesCacheEnabled()`, `placesCacheForce()` and constructor `$apiKey` / `$baseUrl` / `ApiUsageGate` deps.

- [ ] **Step 2: Extract PlacesNicheRankClient**

Move `getTopRankedInNiche()` and any pagination `sleep` — **optional sub-task:** replace `sleep(2)` with `usleep` + config `places_pagination_delay_ms` (REF-S1-08) if touching pagination loop in same PR.

- [ ] **Step 3: Extract PlacesWebsiteLookupClient**

Move `findByWebsiteUrl()`.

- [ ] **Step 4: Thin facade**

```php
class GooglePlacesService
{
    public function __construct(
        private PlacesDetailsClient $details,
        private PlacesNicheRankClient $nicheRank,
        private PlacesWebsiteLookupClient $websiteLookup,
        private ApiUsageGate $usageGate,
    ) {}

    public function searchByNicheAndCity(string $niche, string $city, string $country = 'GB'): array
    {
        return (new PlacesTextSearchClient(...))->searchPlaceIds(...);
    }

    public function getPlaceDetails(string $placeId): ?array
    {
        return $this->details->get($placeId);
    }
    // ...
}
```

Register new clients in `AppServiceProvider` only if manual `new` is awkward — prefer constructor injection on facade.

- [ ] **Step 5: Run tests**

```bash
php artisan test --filter=GooglePlacesServiceTest
php artisan test
```

- [ ] **Step 6: Commit**

```bash
git add app/Services/GooglePlaces/ app/Services/GooglePlacesService.php
git commit -m "refactor: decompose GooglePlacesService into focused clients"
```

---

### Task 8: `NichesBootstrapSteps` decomposition (PR #8)

**Context:** 206-line class mixing taxonomy scraping, allow/block lists, fallback niches, and city fetch. `NichesCityCatalog` already exists.

**Files:**
- Create: `app/Services/Niches/NichesTaxonomyParser.php`
- Modify: `app/Services/NichesBootstrapSteps.php`
- Test: `tests/Feature/NichesBootstrapCommandTest.php`

- [ ] **Step 1: Extract taxonomy parser**

Move to `NichesTaxonomyParser`:
- `TAXONOMY_URL`, `TYPE_BLOCKLIST`, `TYPE_ALLOWLIST`, `FALLBACK_NICHES`
- `fetchNicheCandidates(?callable $warn): array` body (HTTP fetch + HTML parse + filtering)

Public API:

```php
class NichesTaxonomyParser
{
    /** @return list<array{label: string, query: string, primary_type: string}> */
    public function fetchCandidates(?callable $warn = null): array;
}
```

- [ ] **Step 2: Slim orchestrator**

```php
class NichesBootstrapSteps
{
    public function __construct(
        private GooglePlacesService $places,
        private NichesTaxonomyParser $taxonomy,
    ) {}

    public function fetchCities(?callable $warn = null): array
    {
        return (new NichesCityCatalog)->fetchCities($warn);
    }

    public function fetchNicheCandidates(?callable $warn = null): array
    {
        return $this->taxonomy->fetchCandidates($warn);
    }
}
```

- [ ] **Step 3: Run tests**

```bash
php artisan test --filter=NichesBootstrapCommandTest
php artisan test
```

- [ ] **Step 4: Commit**

```bash
git add app/Services/Niches/ app/Services/NichesBootstrapSteps.php
git commit -m "refactor: extract NichesTaxonomyParser from bootstrap steps"
```

---

## Staging verification (after PR #3 and #4)

| PR | Smoke |
|----|-------|
| #3 CMS | Run a `combined` search on staging; confirm `cms_detection` populated on prospect detail without duplicate `/detect-cms` timing in Fly logs |
| #4 Outreach | Queue 5+ prospects; confirm `/outreach` loads < 1s; Generate All still dispatches jobs |
| #5 MCP | `watch_search_progress` via Cursor MCP — progress notifications still arrive; worker releases on complete |

---

## Self-review checklist

| Requirement | Task |
|-------------|------|
| Queue simplify | Task 1 |
| Doc hygiene | Task 2 |
| BrowserServiceClient decomposition | Task 6 |
| GooglePlacesService decomposition | Task 7 |
| NichesBootstrapSteps decomposition | Task 8 |
| Outreach unbounded `->get()` | Task 4 |
| MCP sleep watch loop | Task 5 |
| CMS dual path | Task 3 |

No TBD placeholders. Each PR is independently mergeable.

---

## Execution handoff

Plan saved to `docs/superpowers/plans/2026-06-08-deferred-backlog-followup.md`.

**Two execution options:**

1. **Subagent-driven (recommended)** — one fresh subagent per PR, review between merges
2. **Inline** — execute PR #1–#5 in this session; schedule #6–#8 as separate sessions

Which approach do you want?
