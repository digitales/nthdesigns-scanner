# CMS Detection — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Detect CMS/platform (WordPress-first + six other builders) for every prospect with a `website_url`, persist detailed JSON on `prospects.cms_detection`, and surface operator-only badges on search results and prospect detail.

**Architecture:** Shared `scripts/cms-detect.js` runs inside `audit.js` on the same Playwright navigation; `DetectCmsJob` runs `scripts/detect-cms.js` when no full audit runs (e.g. `gbp_only`) or when `AUDIT_DRIVER=skip`. PHP stores `cms_detection` and shapes display via `ReportBuilderService::cmsForProspect()`. Fly browser service gains `POST /detect-cms` for HTTP driver parity.

**Tech Stack:** Laravel 13, Playwright, Node test runner (`node --test`), Inertia/React, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-06-01-cms-detection-design.md`

---

## File map

| File | Responsibility |
|------|----------------|
| `database/migrations/..._add_cms_detection_to_prospects_table.php` | Nullable JSON column |
| `app/Models/Prospect.php` | `cms_detection` fillable + cast |
| `scripts/cms-detect.js` | Heuristics + `resolveCmsFromInputs()` + `detectCms(page)` |
| `scripts/cms-detect.test.js` | Node unit tests (fixtures, no browser) |
| `scripts/detect-cms.js` | CLI: URL → JSON stdout |
| `scripts/audit.js` | Call `detectCms` after `goto`; add `cms` to payload |
| `scripts/browser-service/server.mjs` | `POST /detect-cms` handler |
| `config/scanner.php` | `cms_detect_script_path` (optional, default `scripts/detect-cms.js`) |
| `app/Services/CmsDetectionRunnerService.php` | Run detect script (local Process / HTTP) |
| `app/Services/BrowserServiceClient.php` | `fetchCmsDetection(string $url)` |
| `app/Jobs/DetectCmsJob.php` | Queue job, `AuditingQueue` |
| `app/Jobs/AuditSiteJob.php` | Persist `cms` from audit payload when present |
| `app/Jobs/ScorePlaceJob.php` | Dispatch `DetectCmsJob` when URL + no `AuditSiteJob` |
| `app/Services/ProspectEnrichmentService.php` | Clear + dispatch detect on URL change |
| `app/Services/ProspectAuditService.php` | `cms_detection` in `auditResetFields()` |
| `app/Services/ReportBuilderService.php` | `cmsForProspect()` |
| `app/Http/Controllers/SearchController.php` | `cms_badge`, `cms_pending` on prospect rows |
| `app/Http/Controllers/ProspectController.php` | `cms` Inertia prop |
| `app/Http/Resources/ProspectListResource.php` | Same fields for saved prospects |
| `resources/js/Components/cms/CmsBadge.jsx` | Table badge |
| `resources/js/Components/cms/TechnologySection.jsx` | Prospect detail block |
| `resources/js/Pages/Search/Show.jsx` | CMS column |
| `resources/js/Pages/Prospect/Show.jsx` | Technology section |
| `tests/fixtures/cms/*.html` | HTML snippets for Node tests |
| `tests/Unit/ReportBuilderServiceTest.php` | `cmsForProspect` tests |
| `tests/Unit/CmsDetectionRunnerServiceTest.php` | HTTP driver mock |
| `tests/Feature/DetectCmsJobTest.php` | Job persistence |
| `tests/Feature/CmsDetectionIntegrationTest.php` | Enrichment + audit payload wiring |

---

### Task 1: Database + model

**Files:**
- Create: `database/migrations/2026_06_01_000000_add_cms_detection_to_prospects_table.php`
- Modify: `app/Models/Prospect.php`
- Modify: `database/factories/ProspectFactory.php` (optional `cms_detection` state)

- [ ] **Step 1: Create migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $table->json('cms_detection')->nullable()->after('raw_lighthouse_payload');
        });
    }

    public function down(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $table->dropColumn('cms_detection');
        });
    }
};
```

- [ ] **Step 2: Update `Prospect` model**

Add to `$fillable`: `'cms_detection'`.

Add to `$casts`: `'cms_detection' => 'array'`.

- [ ] **Step 3: Run migration**

```bash
php artisan migrate
```

Expected: migration OK.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/*_add_cms_detection_to_prospects_table.php app/Models/Prospect.php
git commit -m "feat(cms): add cms_detection JSON column on prospects"
```

---

### Task 2: `cms-detect.js` heuristics + Node tests

**Files:**
- Create: `scripts/cms-detect.js`
- Create: `scripts/cms-detect.test.js`
- Create: `tests/fixtures/cms/wordpress-generator.html`
- Create: `tests/fixtures/cms/shopify-cdn.html`
- Create: `tests/fixtures/cms/unknown-static.html`
- Modify: `scripts/package.json` — add `"cms-detect.test.js"` to `test` script

- [ ] **Step 1: Add fixtures**

`tests/fixtures/cms/wordpress-generator.html` — minimal HTML with:

```html
<!DOCTYPE html><html><head>
<meta name="generator" content="WordPress 6.4.2" />
<link rel="https://api.w.org/" href="/wp-json/" />
</head><body class="wp-singular page-id-12"></body></html>
```

`tests/fixtures/cms/shopify-cdn.html` — include `cdn.shopify.com` and `Shopify.shop` in script/src.

`tests/fixtures/cms/unknown-static.html` — plain HTML, no platform markers.

- [ ] **Step 2: Write failing Node tests**

Create `scripts/cms-detect.test.js`:

```javascript
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import test from 'node:test';
import assert from 'node:assert/strict';
import { resolveCmsFromInputs } from './cms-detect.js';

const fixturesDir = join(dirname(fileURLToPath(import.meta.url)), '..', 'tests', 'fixtures', 'cms');

function load(name) {
    return readFileSync(join(fixturesDir, name), 'utf8');
}

test('detects WordPress with version and high confidence', () => {
    const html = load('wordpress-generator.html');
    const result = resolveCmsFromInputs({
        html,
        bodyClass: 'wp-singular page-id-12',
        headers: {},
        finalUrl: 'https://example.com/',
    });

    assert.equal(result.platform, 'wordpress');
    assert.equal(result.version, '6.4.2');
    assert.equal(result.confidence, 'high');
    assert.ok(result.signals.some((s) => s.id === 'meta_generator' && s.matched));
});

test('detects Shopify from HTML markers', () => {
    const html = load('shopify-cdn.html');
    const result = resolveCmsFromInputs({
        html,
        bodyClass: '',
        headers: {},
        finalUrl: 'https://shop.example.com/',
    });

    assert.equal(result.platform, 'shopify');
});

test('returns unknown when no signals match', () => {
    const html = load('unknown-static.html');
    const result = resolveCmsFromInputs({
        html,
        bodyClass: 'layout',
        headers: {},
        finalUrl: 'https://example.com/',
    });

    assert.equal(result.platform, 'unknown');
    assert.equal(result.confidence, 'low');
});
```

- [ ] **Step 3: Run tests to verify they fail**

```bash
cd scripts && npm test 2>&1 | head -30
```

Expected: FAIL — cannot find module or `resolveCmsFromInputs` not exported.

- [ ] **Step 4: Implement `scripts/cms-detect.js`**

Export:

```javascript
export function resolveCmsFromInputs({ html, bodyClass, headers, finalUrl, error = null }) { ... }

export async function detectCms(page) {
    const response = page.mainFrame() ? await page.evaluate(() => document.documentElement.outerHTML).catch(() => '') : '';
    // Prefer: const html = await page.content();
    const bodyClass = await page.locator('body').getAttribute('class') ?? '';
    const headers = {}; // collect from page.goto response — pass response into detectCms(page, response)
    return resolveCmsFromInputs({ html: await page.content(), bodyClass, headers: responseHeaders(response), finalUrl: page.url(), error: null });
}
```

Implementation requirements:

- Platform enum: `wordpress`, `shopify`, `wix`, `squarespace`, `webflow`, `joomla`, `drupal`, `unknown`.
- Weighted rules per spec; pick highest-scoring platform.
- `signals` array lists every rule with `{ id, matched, detail }`.
- `detected_at` ISO string (UTC).
- On `error` arg set: `platform: unknown`, `confidence: low`, signal `{ id: 'fetch_failed', matched: true, detail: error }`.
- WordPress version: `/WordPress\s+([\d.]+)/i` on generator meta content.
- Body class: match `\bwp-[\w-]+` or `\bpage-id-\d+\b`.
- HTML scan: case-insensitive substring checks for paths/domains in spec.

Signature for audit integration:

```javascript
export async function detectCms(page, response) {
    const html = await page.content();
    const bodyClass = (await page.locator('body').getAttribute('class')) ?? '';
    const headers = response ? Object.fromEntries(
        Object.entries(response.headers()).map(([k, v]) => [k.toLowerCase(), v])
    ) : {};
    return resolveCmsFromInputs({ html, bodyClass, headers, finalUrl: page.url() });
}
```

- [ ] **Step 5: Update `scripts/package.json` test script**

```json
"test": "node --test lighthouse-detail.test.js pagespeed-fetch.test.js cms-detect.test.js"
```

- [ ] **Step 6: Run Node tests**

```bash
cd scripts && npm test
```

Expected: all PASS including new cms tests.

- [ ] **Step 7: Commit**

```bash
git add scripts/cms-detect.js scripts/cms-detect.test.js scripts/package.json tests/fixtures/cms/
git commit -m "feat(cms): add cms-detect heuristics and node tests"
```

---

### Task 3: `detect-cms.js` CLI

**Files:**
- Create: `scripts/detect-cms.js`

- [ ] **Step 1: Create CLI script**

```javascript
#!/usr/bin/env node

import { chromium } from 'playwright';
import { chromiumLaunchOptions } from './browser.js';
import { detectCms } from './cms-detect.js';

const url = process.argv[2];

if (!url) {
    console.error(JSON.stringify({ error: 'URL argument required' }));
    process.exit(1);
}

async function main() {
    const browser = await chromium.launch(chromiumLaunchOptions);
    const context = await browser.newContext();
    const page = await context.newPage();

    try {
        const response = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 45000 });
        const cms = await detectCms(page, response);
        process.stdout.write(JSON.stringify(cms));
    } catch (error) {
        process.stdout.write(JSON.stringify({
            platform: 'unknown',
            version: null,
            confidence: 'low',
            signals: [{ id: 'fetch_failed', matched: true, detail: error.message }],
            detected_at: new Date().toISOString(),
            url,
        }));
        process.exit(1);
    } finally {
        await context.close();
        await browser.close();
    }
}

main();
```

- [ ] **Step 2: Smoke test locally (optional, needs network)**

```bash
node scripts/detect-cms.js https://wordpress.org
```

Expected: JSON with `platform` field (likely `wordpress`).

- [ ] **Step 3: Commit**

```bash
git add scripts/detect-cms.js
git commit -m "feat(cms): add detect-cms playwright CLI"
```

---

### Task 4: Integrate CMS into `audit.js`

**Files:**
- Modify: `scripts/audit.js`

- [ ] **Step 1: Import and call after navigation**

At top:

```javascript
import { detectCms } from './cms-detect.js';
```

In `main()`, after `await page.goto(...)`:

```javascript
const response = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 45000 });
const cms = await detectCms(page, response);
```

Add `cms` to success payload:

```javascript
const payload = {
    url,
    violations: axe.violations,
    pass_count: axe.passes,
    incomplete_count: axe.incomplete,
    violation_screenshots: violationScreenshots,
    lighthouse,
    cms,
};
```

Include `cms: null` or failed detect object in error JSON branch if goto throws before detect (optional).

- [ ] **Step 2: Commit**

```bash
git add scripts/audit.js
git commit -m "feat(cms): detect CMS during site audit"
```

---

### Task 5: `CmsDetectionRunnerService` + config

**Files:**
- Create: `app/Services/CmsDetectionRunnerService.php`
- Modify: `config/scanner.php`
- Create: `tests/Unit/CmsDetectionRunnerServiceTest.php`

- [ ] **Step 1: Write failing PHPUnit test (HTTP driver)**

```php
public function test_run_uses_browser_service_when_audit_driver_is_http(): void
{
    Config::set('scanner.audit_driver', 'http');
    Config::set('scanner.audit_service_url', 'https://browser.example.com');
    Config::set('scanner.audit_service_token', 'secret');

    Http::fake([
        'https://browser.example.com/detect-cms' => Http::response([
            'platform' => 'wordpress',
            'version' => '6.4.2',
            'confidence' => 'high',
            'signals' => [],
            'detected_at' => now()->toIso8601String(),
            'url' => 'https://example.com',
        ], 200),
    ]);

    $result = app(CmsDetectionRunnerService::class)->run('https://example.com');

    $this->assertSame('wordpress', $result['platform']);
    Http::assertSent(fn ($req) => $req->url() === 'https://browser.example.com/detect-cms');
}
```

- [ ] **Step 2: Run test — expect FAIL**

```bash
php artisan test --filter=test_run_uses_browser_service
```

- [ ] **Step 3: Implement service**

Mirror `AuditRunnerService::runPlaywright()` / `runHttp()`:

```php
<?php

namespace App\Services;

use App\Support\PlaywrightEnv;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

class CmsDetectionRunnerService
{
    public function __construct(private BrowserServiceClient $browser) {}

    /** @return array<string, mixed> */
    public function run(string $url): array
    {
        return match (config('scanner.audit_driver')) {
            'http' => $this->browser->fetchCmsDetection($url),
            default => $this->runPlaywright($url),
        };
    }

    /** @return array<string, mixed> */
    private function runPlaywright(string $url): array
    {
        $result = Process::timeout(90)
            ->env(PlaywrightEnv::forProcess())
            ->run([
                config('scanner.node_binary'),
                config('scanner.cms_detect_script_path'),
                $url,
            ]);

        if (! $result->successful()) {
            throw new \RuntimeException('CMS detect script failed: '.trim($result->errorOutput() ?: $result->output()));
        }

        $payload = json_decode($result->output(), true);

        if (! is_array($payload)) {
            throw new \RuntimeException('CMS detect script returned invalid JSON');
        }

        return $payload;
    }
}
```

Add to `config/scanner.php`:

```php
'cms_detect_script_path' => env('CMS_DETECT_SCRIPT_PATH') ?: base_path('scripts/detect-cms.js'),
```

- [ ] **Step 4: Add `BrowserServiceClient::fetchCmsDetection`**

```php
public function fetchCmsDetection(string $url): array
{
    $response = Http::withToken(config('scanner.audit_service_token'))
        ->timeout(config('scanner.cms_detect_timeout', 90))
        ->post($this->endpoint('/detect-cms'), ['url' => $url]);

    $response->throw();

    return $response->json();
}
```

Add optional `cms_detect_timeout` to config (default 90).

- [ ] **Step 5: Run tests**

```bash
php artisan test tests/Unit/CmsDetectionRunnerServiceTest.php tests/Unit/BrowserServiceClientTest.php
```

Extend `BrowserServiceClientTest` with `fetchCmsDetection` happy path if needed.

- [ ] **Step 6: Commit**

```bash
git add app/Services/CmsDetectionRunnerService.php app/Services/BrowserServiceClient.php config/scanner.php tests/Unit/
git commit -m "feat(cms): add CmsDetectionRunnerService and HTTP client"
```

---

### Task 6: Browser service `POST /detect-cms`

**Files:**
- Modify: `scripts/browser-service/server.mjs`
- Modify: `scripts/browser-service/README.md` (one line endpoint list)

- [ ] **Step 1: Add handler**

```javascript
async function handleDetectCms(url) {
    const stdout = await runNodeScript('detect-cms.js', [url]);
    return JSON.parse(stdout);
}
```

In request router after `/audit` block:

```javascript
if (pathname === '/detect-cms') {
    sendJson(res, 200, await handleDetectCms(url));
    return;
}
```

- [ ] **Step 2: Commit**

```bash
git add scripts/browser-service/server.mjs scripts/browser-service/README.md
git commit -m "feat(cms): expose /detect-cms on browser service"
```

Deploy note: Fly image must be redeployed after merge for production HTTP driver.

---

### Task 7: `DetectCmsJob`

**Files:**
- Create: `app/Jobs/DetectCmsJob.php`
- Create: `tests/Feature/DetectCmsJobTest.php`

- [ ] **Step 1: Write failing feature test**

```php
public function test_persists_cms_detection_on_prospect(): void
{
    $prospect = Prospect::factory()->create([
        'website_url' => 'https://example.com',
        'cms_detection' => null,
    ]);

    $this->mock(CmsDetectionRunnerService::class, function ($mock) {
        $mock->shouldReceive('run')
            ->once()
            ->with('https://example.com')
            ->andReturn([
                'platform' => 'wordpress',
                'version' => '6.4.2',
                'confidence' => 'high',
                'signals' => [],
                'detected_at' => now()->toIso8601String(),
                'url' => 'https://example.com',
            ]);
    });

    (new DetectCmsJob($prospect))->handle(app(CmsDetectionRunnerService::class));

    $prospect->refresh();
    $this->assertSame('wordpress', $prospect->cms_detection['platform']);
}
```

Add test `test_skips_when_cms_already_matches_url()` — prospect with matching `cms_detection.url` and normalized URL unchanged → `run` not called.

- [ ] **Step 2: Implement job**

```php
<?php

namespace App\Jobs;

use App\Models\Prospect;
use App\Services\CmsDetectionRunnerService;
use App\Support\AuditingQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DetectCmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $backoff = 30;
    public int $timeout = 120;

    public function __construct(public Prospect $prospect)
    {
        AuditingQueue::apply($this);
    }

    public function handle(CmsDetectionRunnerService $runner): void
    {
        $prospect = $this->prospect->fresh();

        if (! $prospect?->website_url) {
            return;
        }

        if ($this->alreadyDetected($prospect)) {
            return;
        }

        try {
            $payload = $runner->run($prospect->website_url);
            $prospect->update(['cms_detection' => $payload]);
        } catch (\Throwable $e) {
            Log::warning('DetectCmsJob failed', [
                'prospect_id' => $prospect->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function alreadyDetected(Prospect $prospect): bool
    {
        $stored = $prospect->cms_detection;

        if (! is_array($stored) || empty($stored['url'])) {
            return false;
        }

        return $this->normalizeUrl($stored['url']) === $this->normalizeUrl($prospect->website_url);
    }

    private function normalizeUrl(string $url): string
    {
        return Str::lower(rtrim($url, '/'));
    }
}
```

- [ ] **Step 3: Run tests**

```bash
php artisan test tests/Feature/DetectCmsJobTest.php
```

- [ ] **Step 4: Commit**

```bash
git add app/Jobs/DetectCmsJob.php tests/Feature/DetectCmsJobTest.php
git commit -m "feat(cms): add DetectCmsJob"
```

---

### Task 8: Wire jobs and enrichment

**Files:**
- Modify: `app/Jobs/AuditSiteJob.php`
- Modify: `app/Jobs/ScorePlaceJob.php`
- Modify: `app/Services/ProspectEnrichmentService.php`
- Modify: `app/Services/ProspectAuditService.php`
- Create: `tests/Feature/CmsDetectionIntegrationTest.php`

- [ ] **Step 1: `AuditSiteJob` — persist CMS from audit**

After scoring, before `$prospect->update([...])`:

```php
$cmsUpdate = [];
if (isset($payload['cms']) && is_array($payload['cms'])) {
    $cmsUpdate['cms_detection'] = $payload['cms'];
}

$prospect->update(array_merge([
    'a11y_score' => ...,
    // existing fields
], $cmsUpdate));
```

Do not null `cms_detection` on failed audit (spec).

- [ ] **Step 2: `ScorePlaceJob::dispatchNextStep`**

```php
use App\Jobs\DetectCmsJob;

private function dispatchNextStep(Prospect $prospect, Search $search): void
{
    $needsA11yAudit = in_array($search->scan_type, ['accessibility_only', 'combined'], true)
        && ! empty($prospect->website_url);

    if ($needsA11yAudit) {
        AuditSiteJob::dispatch($prospect);

        if (config('scanner.audit_driver') === 'skip') {
            DetectCmsJob::dispatch($prospect);
        }

        return;
    }

    if (! empty($prospect->website_url)) {
        DetectCmsJob::dispatch($prospect);
    }

    CombineScoresJob::dispatch($prospect->fresh());
}
```

- [ ] **Step 3: `ProspectAuditService::auditResetFields`**

Add `'cms_detection' => null`.

- [ ] **Step 4: `ProspectEnrichmentService::update`**

In `$updates` when `$websiteChanged`:

- Merge `'cms_detection' => null` into updates (or in reset block).
- After `$prospect->update`, if `$prospect->website_url` and not `$auditQueued`, `DetectCmsJob::dispatch($prospect)`.
- If `$auditQueued`, rely on audit payload for CMS (do not dispatch detect job).

- [ ] **Step 5: Feature tests**

`tests/Feature/CmsDetectionIntegrationTest.php`:

1. `test_enrichment_clears_cms_and_dispatches_detect_when_url_changes_without_audit` — `gbp_only` search, change URL, assert `DetectCmsJob` pushed and `cms_detection` null after save.
2. `test_audit_site_job_stores_cms_from_payload` — mock `AuditRunnerService` to return payload with `cms` key.

Use `Bus::fake([DetectCmsJob::class, AuditSiteJob::class])` where appropriate.

- [ ] **Step 6: Run tests**

```bash
php artisan test tests/Feature/CmsDetectionIntegrationTest.php tests/Feature/DetectCmsJobTest.php
```

- [ ] **Step 7: Commit**

```bash
git add app/Jobs/ app/Services/ProspectEnrichmentService.php app/Services/ProspectAuditService.php tests/Feature/CmsDetectionIntegrationTest.php
git commit -m "feat(cms): wire detect job into scan and enrichment flows"
```

---

### Task 9: `ReportBuilderService::cmsForProspect`

**Files:**
- Modify: `app/Services/ReportBuilderService.php`
- Modify: `tests/Unit/ReportBuilderServiceTest.php`
- Modify: `tests/Feature/GenerateProspectReportJobTest.php` (assert public report JSON has no `cms` key at top level)

- [ ] **Step 1: Failing unit tests**

```php
public function test_cms_for_prospect_returns_null_without_website(): void
{
    $prospect = Prospect::factory()->make(['website_url' => null]);
    $this->assertNull($this->service->cmsForProspect($prospect));
}

public function test_cms_for_prospect_pending_when_url_but_no_detection(): void
{
    $prospect = Prospect::factory()->make([
        'website_url' => 'https://example.com',
        'cms_detection' => null,
    ]);
    $cms = $this->service->cmsForProspect($prospect);
    $this->assertTrue($cms['pending']);
}

public function test_cms_for_prospect_labels_wordpress_with_version(): void
{
    $prospect = Prospect::factory()->make([
        'website_url' => 'https://example.com',
        'cms_detection' => [
            'platform' => 'wordpress',
            'version' => '6.4.2',
            'confidence' => 'high',
            'signals' => [],
            'detected_at' => '2026-06-01T00:00:00+00:00',
            'url' => 'https://example.com',
        ],
    ]);
    $cms = $this->service->cmsForProspect($prospect);
    $this->assertSame('WordPress 6.4', $cms['label']);
    $this->assertSame('WP', $cms['badge']);
}
```

- [ ] **Step 2: Implement `cmsForProspect`**

Private helpers: `cmsLabel(platform, version)`, `cmsBadge(platform)`.

Return shape per spec; `pending: true` when URL set and `cms_detection` null.

- [ ] **Step 3: Assert `build()` omits CMS**

In existing report test, after `build()`:

```php
$this->assertArrayNotHasKey('cms', $report);
```

- [ ] **Step 4: Run tests**

```bash
php artisan test tests/Unit/ReportBuilderServiceTest.php tests/Feature/GenerateProspectReportJobTest.php
```

- [ ] **Step 5: Commit**

```bash
git add app/Services/ReportBuilderService.php tests/Unit/ReportBuilderServiceTest.php tests/Feature/GenerateProspectReportJobTest.php
git commit -m "feat(cms): shape operator CMS display in ReportBuilderService"
```

---

### Task 10: Operator API props

**Files:**
- Modify: `app/Http/Controllers/ProspectController.php`
- Modify: `app/Http/Controllers/SearchController.php`
- Modify: `app/Http/Resources/ProspectListResource.php`
- Modify: `tests/Feature/ProspectShowTest.php` (or create)

- [ ] **Step 1: `ProspectController@show`**

Inject `cms`:

```php
'cms' => $reportBuilder->cmsForProspect($prospect),
```

- [ ] **Step 2: `SearchController@show` prospect map**

After `website_url`:

```php
'cms_badge' => $reportBuilder->cmsForProspect($p)['badge'] ?? null,
'cms_pending' => $reportBuilder->cmsForProspect($p)['pending'] ?? false,
```

Optimize: call `cmsForProspect` once per prospect into a variable.

- [ ] **Step 3: `ProspectListResource`**

Add same `cms_badge` and `cms_pending` using `app(ReportBuilderService::class)->cmsForProspect($prospect)` or extract shared helper.

- [ ] **Step 4: Feature test**

`ProspectShowTest`: authenticated user sees `cms` key in Inertia props when prospect has detection.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/ app/Http/Resources/ tests/Feature/
git commit -m "feat(cms): expose CMS props to operator Inertia pages"
```

---

### Task 11: React UI

**Files:**
- Create: `resources/js/Components/cms/CmsBadge.jsx`
- Create: `resources/js/Components/cms/TechnologySection.jsx`
- Modify: `resources/js/Pages/Search/Show.jsx`
- Modify: `resources/js/Pages/Prospect/Show.jsx`
- Modify: `resources/css/components.css` (minimal badge styles if needed)

- [ ] **Step 1: `CmsBadge.jsx`**

```jsx
export default function CmsBadge({ badge, pending }) {
    if (!badge && !pending) {
        return <span className="micro">—</span>;
    }
    if (pending) {
        return <span className="badge cms-badge cms-badge--pending">…</span>;
    }
    return <span className="badge cms-badge">{badge}</span>;
}
```

- [ ] **Step 2: Search table column**

In `Search/Show.jsx` header row add `<th>CMS</th>` (always, not only when `showA11y`).

In `ProspectRow`, new `<td><CmsBadge badge={p.cms_badge} pending={p.cms_pending} /></td>`.

- [ ] **Step 3: `TechnologySection.jsx`**

Props: `cms` object from controller.

- Pending: “Detecting platform…”
- Otherwise: label + confidence chip (`High` / `Medium` / `Low`)
- `<details>` for signals list (`id`, matched, detail`)

- [ ] **Step 4: `Prospect/Show.jsx`**

Render `<TechnologySection cms={cms} />` in sidebar (below profile card, above notes) when `cms !== null`.

- [ ] **Step 5: Build frontend**

```bash
npm run build
```

Expected: no build errors.

- [ ] **Step 6: Commit**

```bash
git add resources/js/Components/cms/ resources/js/Pages/ resources/css/components.css
git commit -m "feat(cms): operator CMS badge and technology section"
```

---

### Task 12: Final verification

- [ ] **Run full test suites**

```bash
cd scripts && npm test
cd .. && php artisan test
```

- [ ] **Manual smoke (local)**

1. Run `gbp_only` search with a WordPress business URL → table shows `WP` after job.
2. Run `combined` search → CMS appears without separate wait when audit completes.
3. Open prospect detail → Technology block with signals.
4. Open `/r/{token}` → no CMS line.

- [ ] **Update spec status**

In `docs/superpowers/specs/2026-06-01-cms-detection-design.md`, set **Status:** Implemented.

---

## Follow-up (out of scope)

- `php artisan scanner:backfill-cms` for existing prospects
- Search filter “WordPress only”
- Outreach email prompt mentions CMS

---

## Plan self-review (spec coverage)

| Spec requirement | Task |
|------------------|------|
| Detailed JSON contract | Task 2, 7 |
| WordPress + 6 platforms | Task 2 |
| Detect on all URLs | Task 7–8 |
| audit.js same navigation | Task 4 |
| DetectCmsJob gbp_only | Task 7–8 |
| Operator UI table + detail | Task 11 |
| No public report CMS | Task 9 |
| Browser service HTTP | Task 5–6 |
| URL change clears | Task 8 |
| audit reset clears | Task 8 |
| Failed audit no CMS overwrite | Task 8 |
| AUDIT_DRIVER=skip | Task 8 ScorePlaceJob branch |

No TBD placeholders in task steps.
