# Single-Site URL Audit — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let operators submit a website URL from `/search`, attempt GBP lookup, run the full audit/report pipeline for one prospect, and land on `/searches/{id}`.

**Architecture:** Add `source` + `submitted_url` to `searches`; `WebsiteUrlNormalizer` canonicalises URLs; `GooglePlacesService::findByWebsiteUrl()` resolves GBP via Text Search + host match; `DirectUrlScanJob` creates one prospect and dispatches existing `AuditSiteJob`. Secondary UI card on `Search/Index.jsx`; conditional copy on `Search/Show.jsx`.

**Tech Stack:** Laravel 13, Inertia.js, React, PHPUnit, queue (`SearchQueue` for discovery job routing — direct job uses same `SearchQueue` as `ScrapeProspectsJob`).

**Spec:** `docs/superpowers/specs/2026-05-28-single-site-url-audit-design.md`

---

## File map

| File | Action | Responsibility |
|------|--------|----------------|
| `database/migrations/2026_05_28_120000_add_direct_url_fields_to_searches_table.php` | Create | `source`, `submitted_url`, nullable `niche`/`city` |
| `app/Models/Search.php` | Modify | Fillable + helper |
| `database/factories/SearchFactory.php` | Modify | Default `source=discovery`; add `directUrl()` state |
| `app/Support/WebsiteUrlNormalizer.php` | Create | URL canonicalisation, host extraction, display name |
| `tests/Unit/WebsiteUrlNormalizerTest.php` | Create | Normaliser unit tests |
| `app/Services/GooglePlacesService.php` | Modify | `findByWebsiteUrl()` |
| `tests/Unit/GooglePlacesServiceTest.php` | Create | GBP lookup unit tests |
| `app/Jobs/DirectUrlScanJob.php` | Create | Direct scan orchestration |
| `tests/Feature/DirectUrlScanJobTest.php` | Create | Job feature tests |
| `app/Http/Requests/StoreDirectUrlSearchRequest.php` | Create | URL validation |
| `app/Http/Controllers/SearchController.php` | Modify | `storeDirectUrl`, pass `source`/`submitted_url` to Inertia |
| `routes/web.php` | Modify | `POST /searches/direct` |
| `tests/Feature/DirectUrlSearchTest.php` | Create | HTTP + rate limit tests |
| `tests/Feature/SearchRateLimitTest.php` | Modify | Shared limiter between area + direct |
| `resources/js/Pages/Search/Index.jsx` | Modify | Single-site card + recent search labels |
| `resources/js/Pages/Search/Show.jsx` | Modify | Direct URL header/progress copy |

---

### Task 1: Migration and Search model

**Files:**
- Create: `database/migrations/2026_05_28_120000_add_direct_url_fields_to_searches_table.php`
- Modify: `app/Models/Search.php`
- Modify: `database/factories/SearchFactory.php`

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
        Schema::table('searches', function (Blueprint $table) {
            $table->string('source')->default('discovery')->after('user_id');
            $table->string('submitted_url')->nullable()->after('source');
            $table->string('niche')->nullable()->change();
            $table->string('city')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('searches', function (Blueprint $table) {
            $table->dropColumn(['source', 'submitted_url']);
            $table->string('niche')->nullable(false)->change();
            $table->string('city')->nullable(false)->change();
        });
    }
};
```

- [ ] **Step 2: Update `Search` model**

Add to `$fillable`: `'source'`, `'submitted_url'`.

Add helper:

```php
public function isDirectUrl(): bool
{
    return $this->source === 'direct_url';
}
```

- [ ] **Step 3: Update `SearchFactory`**

Add `'source' => 'discovery'` to `definition()`.

Add state:

```php
public function directUrl(string $url = 'https://example.com'): static
{
    return $this->state(fn () => [
        'source'        => 'direct_url',
        'submitted_url' => $url,
        'niche'         => null,
        'city'          => null,
        'scan_type'     => 'combined',
        'total_found'   => 1,
    ]);
}
```

- [ ] **Step 4: Run migration**

```bash
php artisan migrate
```

Expected: migration OK.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_05_28_120000_add_direct_url_fields_to_searches_table.php app/Models/Search.php database/factories/SearchFactory.php
git commit -m "feat(searches): add direct_url source and submitted_url columns"
```

---

### Task 2: WebsiteUrlNormalizer

**Files:**
- Create: `app/Support/WebsiteUrlNormalizer.php`
- Create: `tests/Unit/WebsiteUrlNormalizerTest.php`

- [ ] **Step 1: Write failing tests**

```php
<?php

namespace Tests\Unit;

use App\Support\WebsiteUrlNormalizer;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WebsiteUrlNormalizerTest extends TestCase
{
    private WebsiteUrlNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new WebsiteUrlNormalizer();
    }

    #[DataProvider('normalizeProvider')]
    public function test_normalize(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->normalizer->normalize($input));
    }

    public static function normalizeProvider(): array
    {
        return [
            'bare domain'           => ['example.com', 'https://example.com'],
            'https with www'        => ['https://www.example.com', 'https://example.com'],
            'http with path'        => ['http://www.example.com/about', 'http://example.com/about'],
            'https with path'       => ['https://example.com/about/', 'https://example.com/about'],
        ];
    }

    public function test_host_strips_www(): void
    {
        $this->assertSame('example.com', $this->normalizer->host('https://www.example.com/foo'));
    }

    public function test_display_name_from_url(): void
    {
        $name = $this->normalizer->displayNameFromUrl('https://birminghamdentalpractice.co.uk');
        $this->assertSame('Birminghamdentalpractice', $name);
    }

    public function test_rejects_non_http_scheme(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->normalizer->normalize('javascript:alert(1)');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test tests/Unit/WebsiteUrlNormalizerTest.php
```

Expected: FAIL — class not found.

- [ ] **Step 3: Implement normalizer**

```php
<?php

namespace App\Support;

use InvalidArgumentException;

final class WebsiteUrlNormalizer
{
    public function normalize(string $input): string
    {
        $trimmed = trim($input);

        if ($trimmed === '') {
            throw new InvalidArgumentException('URL is required.');
        }

        if (! preg_match('#^https?://#i', $trimmed)) {
            $trimmed = 'https://'.$trimmed;
        }

        $parts = parse_url($trimmed);

        if ($parts === false || empty($parts['host'])) {
            throw new InvalidArgumentException('Invalid URL.');
        }

        $scheme = strtolower($parts['scheme'] ?? '');

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('URL must use http or https.');
        }

        $host = strtolower($parts['host']);

        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        $path = $parts['path'] ?? '';

        if ($path === '/') {
            $path = '';
        }

        $path = rtrim($path, '/');

        return $scheme.'://'.$host.$path;
    }

    public function host(string $url): string
    {
        $normalized = $this->normalize($url);
        $host = parse_url($normalized, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            throw new InvalidArgumentException('Invalid URL host.');
        }

        $host = strtolower($host);

        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        return $host;
    }

    public function displayNameFromUrl(string $url): string
    {
        $host = $this->host($url);
        $label = preg_replace('/\.(co\.uk|org\.uk|com|org|net|uk|ie)$/i', '', $host) ?? $host;
        $label = str_replace(['-', '.'], ' ', $label);

        return ucwords($label);
    }
}
```

- [ ] **Step 4: Run tests**

```bash
php artisan test tests/Unit/WebsiteUrlNormalizerTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Support/WebsiteUrlNormalizer.php tests/Unit/WebsiteUrlNormalizerTest.php
git commit -m "feat: add WebsiteUrlNormalizer for direct URL scans"
```

---

### Task 3: GooglePlacesService::findByWebsiteUrl

**Files:**
- Modify: `app/Services/GooglePlacesService.php`
- Create: `tests/Unit/GooglePlacesServiceTest.php`

- [ ] **Step 1: Write failing tests**

```php
<?php

namespace Tests\Unit;

use App\Services\GooglePlacesService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GooglePlacesServiceTest extends TestCase
{
    public function test_find_by_website_url_returns_details_on_host_match(): void
    {
        config(['services.google_places.key' => 'test-key']);

        Http::fake([
            'https://places.googleapis.com/v1/places:searchText' => Http::response([
                'places' => [
                    [
                        'id'          => 'places/abc',
                        'websiteUri'  => 'https://www.example.com',
                        'displayName' => ['text' => 'Example Ltd'],
                    ],
                ],
            ], 200),
            'https://places.googleapis.com/v1/places/places/abc' => Http::response([
                'id'          => 'places/abc',
                'displayName' => ['text' => 'Example Ltd'],
                'websiteUri'  => 'https://example.com',
                'userRatingCount' => 10,
                'photos'      => [],
            ], 200),
        ]);

        $result = app(GooglePlacesService::class)->findByWebsiteUrl('https://example.com');

        $this->assertNotNull($result);
        $this->assertSame('places/abc', $result['id']);
    }

    public function test_find_by_website_url_returns_null_when_no_host_match(): void
    {
        config(['services.google_places.key' => 'test-key']);

        Http::fake([
            'https://places.googleapis.com/v1/places:searchText' => Http::response([
                'places' => [
                    [
                        'id'         => 'places/other',
                        'websiteUri' => 'https://other.com',
                    ],
                ],
            ], 200),
        ]);

        $result = app(GooglePlacesService::class)->findByWebsiteUrl('https://example.com');

        $this->assertNull($result);
    }

    public function test_find_by_website_url_returns_null_on_api_failure(): void
    {
        config(['services.google_places.key' => 'test-key']);

        Http::fake([
            'https://places.googleapis.com/v1/places:searchText' => Http::response([], 500),
        ]);

        $result = app(GooglePlacesService::class)->findByWebsiteUrl('https://example.com');

        $this->assertNull($result);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test tests/Unit/GooglePlacesServiceTest.php
```

Expected: FAIL — method not defined.

- [ ] **Step 3: Add method to `GooglePlacesService`**

```php
use App\Support\WebsiteUrlNormalizer;

public function findByWebsiteUrl(string $url): ?array
{
    $normalizer = app(WebsiteUrlNormalizer::class);
    $targetHost = $normalizer->host($url);

    $response = Http::withHeaders([
        'Content-Type'     => 'application/json',
        'X-Goog-Api-Key'   => $this->apiKey,
        'X-Goog-FieldMask' => 'places.id,places.websiteUri,places.displayName',
    ])->post("{$this->baseUrl}:searchText", [
        'textQuery'      => $targetHost,
        'maxResultCount' => 20,
    ]);

    if ($response->failed()) {
        Log::warning('GooglePlaces findByWebsiteUrl searchText failed', [
            'status' => $response->status(),
            'body'   => $response->body(),
            'host'   => $targetHost,
        ]);

        return null;
    }

    foreach ($response->json('places') ?? [] as $place) {
        $websiteUri = $place['websiteUri'] ?? null;
        $placeId = $place['id'] ?? null;

        if (! $websiteUri || ! $placeId) {
            continue;
        }

        try {
            $placeHost = $normalizer->host($websiteUri);
        } catch (\InvalidArgumentException) {
            continue;
        }

        if ($placeHost === $targetHost) {
            return $this->getPlaceDetails($placeId);
        }
    }

    return null;
}
```

- [ ] **Step 4: Run tests**

```bash
php artisan test tests/Unit/GooglePlacesServiceTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/GooglePlacesService.php tests/Unit/GooglePlacesServiceTest.php
git commit -m "feat(places): add findByWebsiteUrl for direct URL GBP lookup"
```

---

### Task 4: DirectUrlScanJob

**Files:**
- Create: `app/Jobs/DirectUrlScanJob.php`
- Create: `tests/Feature/DirectUrlScanJobTest.php`

- [ ] **Step 1: Write failing tests**

```php
<?php

namespace Tests\Feature;

use App\Jobs\AuditSiteJob;
use App\Jobs\DirectUrlScanJob;
use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use App\Services\GooglePlacesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class DirectUrlScanJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_prospect_with_gbp_when_place_found(): void
    {
        Bus::fake([AuditSiteJob::class]);

        $user = User::factory()->create();
        $search = Search::factory()->directUrl('https://example.com')->create([
            'user_id' => $user->id,
            'status'  => 'pending',
        ]);

        $this->mock(GooglePlacesService::class, function ($mock) {
            $mock->shouldReceive('findByWebsiteUrl')
                ->once()
                ->with('https://example.com')
                ->andReturn([
                    'id'              => 'places/abc',
                    'displayName'     => ['text' => 'Example Ltd'],
                    'websiteUri'      => 'https://example.com',
                    'userRatingCount' => 5,
                    'photos'          => [],
                ]);
        });

        (new DirectUrlScanJob($search))->handle(
            app(GooglePlacesService::class),
            app(\App\Services\GbpScoringService::class),
            app(\App\Services\SearchStatusService::class),
            app(\App\Support\WebsiteUrlNormalizer::class),
        );

        $prospect = Prospect::where('search_id', $search->id)->first();

        $this->assertNotNull($prospect);
        $this->assertSame('places/abc', $prospect->place_id);
        $this->assertSame('https://example.com', $prospect->website_url);
        $this->assertGreaterThan(0, $prospect->gbp_score);
        $this->assertSame(1, $search->fresh()->total_found);
        Bus::assertDispatched(AuditSiteJob::class);
    }

    public function test_creates_prospect_without_gbp_when_not_found(): void
    {
        Bus::fake([AuditSiteJob::class]);

        $user = User::factory()->create();
        $search = Search::factory()->directUrl('https://unknown.example')->create([
            'user_id' => $user->id,
            'status'  => 'pending',
        ]);

        $this->mock(GooglePlacesService::class, function ($mock) {
            $mock->shouldReceive('findByWebsiteUrl')
                ->once()
                ->andReturn(null);
        });

        (new DirectUrlScanJob($search))->handle(
            app(GooglePlacesService::class),
            app(\App\Services\GbpScoringService::class),
            app(\App\Services\SearchStatusService::class),
            app(\App\Support\WebsiteUrlNormalizer::class),
        );

        $prospect = Prospect::where('search_id', $search->id)->first();

        $this->assertNotNull($prospect);
        $this->assertStringStartsWith('direct:', $prospect->place_id);
        $this->assertSame(0, $prospect->gbp_score);
        $this->assertSame(['No GBP match found'], $prospect->gbp_flags);
        $this->assertSame('https://unknown.example', $prospect->website_url);
        Bus::assertDispatched(AuditSiteJob::class);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test tests/Feature/DirectUrlScanJobTest.php
```

Expected: FAIL — class not found.

- [ ] **Step 3: Implement job**

```php
<?php

namespace App\Jobs;

use App\Models\Prospect;
use App\Models\Search;
use App\Support\SearchQueue;
use App\Support\WebsiteUrlNormalizer;
use App\Services\GbpScoringService;
use App\Services\GooglePlacesService;
use App\Services\SearchStatusService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DirectUrlScanJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 15;

    public function __construct(public Search $search)
    {
        SearchQueue::apply($this);
    }

    public function handle(
        GooglePlacesService $places,
        GbpScoringService $scorer,
        SearchStatusService $searchStatus,
        WebsiteUrlNormalizer $normalizer,
    ): void {
        $search = $this->search->fresh();

        if (! $search || ! $search->isDirectUrl() || ! $search->submitted_url) {
            return;
        }

        $search->update(['status' => 'discovering']);

        $url = $search->submitted_url;
        $payload = $places->findByWebsiteUrl($url);

        try {
            if ($payload) {
                $overlay = $scorer->overlayProspectFields($payload, new Prospect(['website_url' => $url]));
                $fields = $scorer->extractFields($overlay);
                $scored = $scorer->score($overlay, null, '');

                $prospect = Prospect::create(array_merge($fields, [
                    'search_id'       => $search->id,
                    'place_id'        => $payload['id'],
                    'website_url'     => $url,
                    'gbp_score'       => $scored['score'],
                    'gbp_flags'       => $scored['flags'],
                    'raw_gbp_payload' => $payload,
                    'expires_at'      => now()->addDays(config('scanner.report_expiry_days', 30)),
                    'audit_status'    => 'pending',
                ]));
            } else {
                $prospect = Prospect::create([
                    'search_id'    => $search->id,
                    'place_id'     => 'direct:'.hash('sha256', $normalizer->normalize($url)),
                    'business_name'=> $normalizer->displayNameFromUrl($url),
                    'website_url'  => $url,
                    'gbp_score'    => 0,
                    'gbp_flags'    => ['No GBP match found'],
                    'raw_gbp_payload' => null,
                    'expires_at'   => now()->addDays(config('scanner.report_expiry_days', 30)),
                    'audit_status' => 'pending',
                ]);
            }

            $search->update(['total_found' => 1]);
            AuditSiteJob::dispatch($prospect);
            $searchStatus->refresh($search->fresh());
        } catch (\Throwable $e) {
            Log::error('DirectUrlScanJob failed', [
                'search_id' => $search->id,
                'error'     => $e->getMessage(),
            ]);
            $search->update(['status' => 'failed']);
            throw $e;
        }
    }
}
```

- [ ] **Step 4: Run tests**

```bash
php artisan test tests/Feature/DirectUrlScanJobTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Jobs/DirectUrlScanJob.php tests/Feature/DirectUrlScanJobTest.php
git commit -m "feat: add DirectUrlScanJob for single-site URL audits"
```

---

### Task 5: HTTP endpoint and rate limiting

**Files:**
- Create: `app/Http/Requests/StoreDirectUrlSearchRequest.php`
- Modify: `app/Http/Controllers/SearchController.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/DirectUrlSearchTest.php`
- Modify: `tests/Feature/SearchRateLimitTest.php`

- [ ] **Step 1: Write failing feature test**

```php
<?php

namespace Tests\Feature;

use App\Jobs\DirectUrlScanJob;
use App\Models\Search;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class DirectUrlSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_direct_url_creates_search_and_dispatches_job(): void
    {
        Bus::fake();
        $user = User::factory()->create();
        RateLimiter::clear('search-submit:'.$user->id);

        $response = $this->actingAs($user)->post('/searches/direct', [
            'website_url' => 'example.com',
        ]);

        $search = Search::first();
        $this->assertNotNull($search);
        $this->assertSame('direct_url', $search->source);
        $this->assertSame('https://example.com', $search->submitted_url);
        $this->assertSame('combined', $search->scan_type);
        $this->assertNull($search->niche);
        $this->assertNull($search->city);

        $response->assertRedirect(route('searches.show', $search));
        Bus::assertDispatched(DirectUrlScanJob::class);
    }

    public function test_store_direct_url_validates_website_url(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/searches/direct', ['website_url' => ''])
            ->assertSessionHasErrors('website_url');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test tests/Feature/DirectUrlSearchTest.php
```

Expected: FAIL — 404 or route missing.

- [ ] **Step 3: Create form request**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDirectUrlSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'website_url' => ['required', 'string', 'max:2048', 'url'],
        ];
    }
}
```

Note: bare `example.com` fails Laravel `url` rule — controller normalises after validation using a custom rule or preprocess. **Use this adjusted validation** instead:

```php
public function rules(): array
{
    return [
        'website_url' => ['required', 'string', 'max:2048', 'regex:/^(https?:\/\/)?[^\s\/]+\.[^\s\/]+/i'],
    ];
}
```

Controller normalises via `WebsiteUrlNormalizer` after validation passes.

- [ ] **Step 4: Add controller method**

In `SearchController.php`:

```php
use App\Http\Requests\StoreDirectUrlSearchRequest;
use App\Jobs\DirectUrlScanJob;
use App\Support\WebsiteUrlNormalizer;

public function storeDirectUrl(StoreDirectUrlSearchRequest $request, WebsiteUrlNormalizer $normalizer): RedirectResponse
{
    $user = $request->user();
    $rateKey = 'search-submit:'.$user->id;
    $decay = config('scanner.search_rate_limit_seconds', 30);

    if (RateLimiter::tooManyAttempts($rateKey, 1)) {
        $seconds = RateLimiter::availableIn($rateKey);

        throw ValidationException::withMessages([
            'website_url' => "Please wait {$seconds} seconds before starting another search.",
        ]);
    }

    $url = $normalizer->normalize($request->validated('website_url'));

    RateLimiter::hit($rateKey, $decay);

    $search = $user->searches()->create([
        'source'        => 'direct_url',
        'submitted_url' => $url,
        'country'       => $this->settings->defaultCountry($user),
        'scan_type'     => 'combined',
        'status'        => 'pending',
        'total_found'   => 1,
    ]);

    DirectUrlScanJob::dispatch($search);

    return redirect()->route('searches.show', $search);
}
```

- [ ] **Step 5: Add route**

In `routes/web.php` inside auth group, after `searches.store`:

```php
Route::post('/searches/direct', [SearchController::class, 'storeDirectUrl'])->name('searches.store-direct');
```

- [ ] **Step 6: Update `SearchController@index` recent searches map**

Add to each item:

```php
'source'         => $s->source,
'submitted_url'  => $s->submitted_url,
```

- [ ] **Step 7: Update `SearchController@show` search payload**

Add:

```php
'source'        => $search->source,
'submitted_url' => $search->submitted_url,
```

- [ ] **Step 8: Extend rate limit test**

Add to `SearchRateLimitTest.php`:

```php
public function test_direct_url_shares_rate_limit_with_area_search(): void
{
    config(['scanner.search_rate_limit_seconds' => 30]);

    $user = User::factory()->create();
    RateLimiter::clear('search-submit:'.$user->id);

    $this->actingAs($user)->post('/searches', [
        'niche'     => 'dental',
        'city'      => 'Leeds',
        'country'   => 'GB',
        'scan_type' => 'combined',
    ])->assertRedirect();

    $this->actingAs($user)
        ->post('/searches/direct', ['website_url' => 'https://example.com'])
        ->assertSessionHasErrors('website_url');
}
```

- [ ] **Step 9: Run tests**

```bash
php artisan test tests/Feature/DirectUrlSearchTest.php tests/Feature/SearchRateLimitTest.php
```

Expected: PASS.

- [ ] **Step 10: Commit**

```bash
git add app/Http/Requests/StoreDirectUrlSearchRequest.php app/Http/Controllers/SearchController.php routes/web.php tests/Feature/DirectUrlSearchTest.php tests/Feature/SearchRateLimitTest.php
git commit -m "feat(search): add POST /searches/direct endpoint with shared rate limit"
```

---

### Task 6: Search/Index.jsx — single-site card

**Files:**
- Modify: `resources/js/Pages/Search/Index.jsx`

- [ ] **Step 1: Add second form below area scan card**

After the closing `</Card>` of the Parameters form (still inside the left column), add:

```jsx
<Card title="Single site audit" style={{ marginTop: 24 }}>
    <p className="micro" style={{ marginBottom: 16, lineHeight: 1.55 }}>
        Paste a website URL to look up its Google Business Profile and run a WCAG 2.2 audit. Takes about 90 seconds.
    </p>
    <form onSubmit={submitDirect}>
        <Field label="Website URL">
            <Input
                value={directForm.data.website_url}
                onChange={(e) => directForm.setData('website_url', e.target.value)}
                placeholder="https://example.co.uk"
                required
            />
            <FormError message={directForm.errors.website_url} />
        </Field>
        <div style={{ marginTop: 16 }}>
            <Button kind="secondary" size="lg" type="submit" disabled={directForm.processing} className="w-full justify-center">
                {directForm.processing ? 'Starting audit…' : 'Run single-site audit'}
            </Button>
        </div>
    </form>
</Card>
```

At top of component, add second form:

```jsx
const directForm = useForm({ website_url: '' });

const submitDirect = (e) => {
    e.preventDefault();
    directForm.post('/searches/direct');
};
```

- [ ] **Step 2: Update recent searches sidebar**

Replace niche/city display:

```jsx
<div style={{ fontWeight: 500, fontSize: 13 }}>
    {s.source === 'direct_url'
        ? (s.submitted_url?.replace(/^https?:\/\//, '') ?? 'Single site')
        : s.niche}
</div>
<div className="micro" style={{ marginTop: 4 }}>
    {s.source === 'direct_url' ? 'Single site' : `${s.city} · ${s.created_at}`}
    {s.source !== 'direct_url' && null}
    {s.source === 'direct_url' ? ` · ${s.created_at}` : null}
</div>
```

Cleaner version:

```jsx
{s.source === 'direct_url' ? (
    <>
        <div style={{ fontWeight: 500, fontSize: 13 }}>{s.submitted_url?.replace(/^https?:\/\//, '') ?? 'Single site'}</div>
        <div className="micro" style={{ marginTop: 4 }}>Single site · {s.created_at}</div>
    </>
) : (
    <>
        <div style={{ fontWeight: 500, fontSize: 13 }}>{s.niche}</div>
        <div className="micro" style={{ marginTop: 4 }}>{s.city} · {s.created_at}</div>
    </>
)}
```

- [ ] **Step 3: Build frontend**

```bash
npm run build
```

Expected: build OK.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Search/Index.jsx
git commit -m "feat(ui): add single-site audit card on search index"
```

---

### Task 7: Search/Show.jsx — direct URL copy

**Files:**
- Modify: `resources/js/Pages/Search/Show.jsx`

- [ ] **Step 1: Add display helpers at top of component**

```jsx
const isDirectUrl = search.source === 'direct_url';
const directHost = search.submitted_url?.replace(/^https?:\/\//, '') ?? 'Single site';
const pageTitle = isDirectUrl ? directHost : `${search.niche} in ${search.city}`;
const eyebrow = isDirectUrl ? `B · Single site · ${directHost}` : `B · ${search.niche} · ${search.city}`;
const runningTitle = isDirectUrl ? 'Auditing website…' : 'Auditing…';
const runningSub = isDirectUrl
    ? 'Looking up Google Business Profile and running the WCAG 2.2 audit. Results appear when complete.'
    : 'Discovering businesses on Google, then running audits in parallel. Rows appear as their audits complete.';
const completeTitle = isDirectUrl
    ? (prospects.length > 0 ? 'Audit complete.' : 'Waiting for audit…')
    : `${scanned} prospects scanned.`;
```

- [ ] **Step 2: Wire into JSX**

Replace hard-coded title/eyebrow/sub strings with the variables above.

Update progress bar strong text when running:

```jsx
<strong>{isDirectUrl ? 'Auditing website' : 'Auditing websites'}</strong>
```

For direct URL running state with `total_found=1`, progress meta can read `scanned {scanned} of 1`.

- [ ] **Step 3: Build frontend**

```bash
npm run build
```

Expected: build OK.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Search/Show.jsx
git commit -m "feat(ui): direct URL search results header and progress copy"
```

---

### Task 8: Full test suite and manual smoke check

**Files:** (none new)

- [ ] **Step 1: Run full PHPUnit suite**

```bash
php artisan test
```

Expected: all tests PASS.

- [ ] **Step 2: Manual smoke check (local)**

1. Log in, visit `/search`.
2. Submit `example.com` in single-site card → redirects to `/searches/{id}`.
3. Confirm page shows hostname header and polls while running.
4. Confirm area scan form still works.

- [ ] **Step 3: Final commit if any fixes needed**

```bash
git add -A
git commit -m "fix: address single-site URL audit review feedback"
```

(Skip if no fixes.)

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| `searches.source` + `submitted_url` migration | Task 1 |
| Nullable niche/city for direct scans | Task 1 |
| `WebsiteUrlNormalizer` | Task 2 |
| `findByWebsiteUrl()` | Task 3 |
| `DirectUrlScanJob` + audit dispatch | Task 4 |
| No GBP → synthetic place_id, flag, audit proceeds | Task 4 |
| `POST /searches/direct` + validation | Task 5 |
| Shared rate limit | Task 5 |
| Redirect to `searches.show` | Task 5 |
| Secondary card on `/search` | Task 6 |
| Recent searches direct URL label | Task 6 |
| Show page direct URL copy | Task 7 |
| Auto-report via existing pipeline | Unchanged — verified in Task 8 smoke |
