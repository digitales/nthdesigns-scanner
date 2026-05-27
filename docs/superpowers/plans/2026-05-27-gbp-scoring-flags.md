# GBP Scoring Flags Expansion — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extend GBP weakness scoring with new absolute flags, benchmark-relative flags, and per-search `benchmark_snapshot` caching so every flag updates `gbp_score` and `gbp_flags`.

**Architecture:** `BenchmarkNormalizer` normalises Places payloads once per search; `GbpScoringService` splits into `scoreAbsolute` + `scoreRelative`, merged in `score($payload, ?array $benchmark, string $city)`; `ScrapeProspectsJob` stores the benchmark before dispatching `ScorePlaceJob`.

**Tech Stack:** Laravel 13, PHPUnit, Places API (New) via `GooglePlacesService`, PostgreSQL/SQLite JSON column.

**Spec:** `docs/superpowers/specs/2026-05-27-gbp-scoring-flags-design.md`

---

## File map

| File | Responsibility |
|------|----------------|
| `database/migrations/2026_05_27_000000_add_benchmark_snapshot_to_searches_table.php` | `benchmark_snapshot` JSON column |
| `app/Models/Search.php` | `$fillable`, `benchmark_snapshot` array cast |
| `app/Services/BenchmarkNormalizer.php` | `fromPlace(array $place): array` snapshot shape |
| `app/Services/ReportBuilderService.php` | Use `BenchmarkNormalizer` instead of inline array |
| `app/Services/GooglePlacesService.php` | Add `businessStatus` to Details field mask |
| `app/Services/GbpScoringService.php` | Layered rubric + website allowlist |
| `app/Jobs/ScrapeProspectsJob.php` | Fetch benchmark, persist snapshot |
| `app/Jobs/ScorePlaceJob.php` | Pass `$search->benchmark_snapshot` and city to scorer |
| `tests/Unit/GbpScoringServiceTest.php` | Rubric unit tests |
| `tests/Unit/BenchmarkNormalizerTest.php` | Normaliser unit tests |
| `tests/Feature/ScrapeProspectsJobTest.php` | Benchmark persistence via HTTP fake |

---

### Task 1: Migration and Search model

**Files:**
- Create: `database/migrations/2026_05_27_000000_add_benchmark_snapshot_to_searches_table.php`
- Modify: `app/Models/Search.php`

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
            $table->json('benchmark_snapshot')->nullable()->after('total_found');
        });
    }

    public function down(): void
    {
        Schema::table('searches', function (Blueprint $table) {
            $table->dropColumn('benchmark_snapshot');
        });
    }
};
```

- [ ] **Step 2: Run migration**

```bash
php artisan migrate
```

Expected: `benchmark_snapshot` column on `searches`.

- [ ] **Step 3: Update Search model**

In `app/Models/Search.php`:

```php
protected $fillable = [
    'user_id', 'niche', 'city', 'country', 'scan_type', 'status', 'total_found',
    'benchmark_snapshot',
];

protected function casts(): array
{
    return [
        'benchmark_snapshot' => 'array',
    ];
}
```

(Use existing `casts()` style in the model — merge if method already exists.)

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_05_27_000000_add_benchmark_snapshot_to_searches_table.php app/Models/Search.php
git commit -m "feat(search): add benchmark_snapshot for GBP relative scoring"
```

---

### Task 2: BenchmarkNormalizer

**Files:**
- Create: `app/Services/BenchmarkNormalizer.php`
- Create: `tests/Unit/BenchmarkNormalizerTest.php`
- Modify: `app/Services/ReportBuilderService.php`

- [ ] **Step 1: Write failing normaliser test**

Create `tests/Unit/BenchmarkNormalizerTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Services\BenchmarkNormalizer;
use PHPUnit\Framework\TestCase;

class BenchmarkNormalizerTest extends TestCase
{
    public function test_from_place_maps_fields(): void
    {
        $place = [
            'id' => 'places/ChIJ123',
            'displayName' => ['text' => 'Top Dental'],
            'rating' => 4.8,
            'userRatingCount' => 312,
            'photos' => [[], [], []],
            'editorialSummary' => ['text' => 'Leading practice'],
            'regularOpeningHours' => ['periods' => [['open' => ['day' => 1]]]],
        ];

        $snapshot = (new BenchmarkNormalizer())->fromPlace($place);

        $this->assertSame('places/ChIJ123', $snapshot['place_id']);
        $this->assertSame('Top Dental', $snapshot['name']);
        $this->assertSame(312, $snapshot['review_count']);
        $this->assertSame(3, $snapshot['photo_count']);
        $this->assertSame(4.8, $snapshot['rating']);
        $this->assertTrue($snapshot['has_description']);
        $this->assertTrue($snapshot['hours_complete']);
    }
}
```

- [ ] **Step 2: Run test — expect fail**

```bash
./vendor/bin/phpunit tests/Unit/BenchmarkNormalizerTest.php -v
```

Expected: FAIL — class not found.

- [ ] **Step 3: Implement BenchmarkNormalizer**

Create `app/Services/BenchmarkNormalizer.php`:

```php
<?php

namespace App\Services;

class BenchmarkNormalizer
{
    /**
     * @return array{
     *     place_id: string|null,
     *     name: string,
     *     review_count: int,
     *     photo_count: int,
     *     rating: float|null,
     *     has_description: bool,
     *     hours_complete: bool
     * }
     */
    public function fromPlace(array $place): array
    {
        return [
            'place_id'        => $place['id'] ?? null,
            'name'            => $place['displayName']['text'] ?? 'Top local competitor',
            'rating'          => isset($place['rating']) ? (float) $place['rating'] : null,
            'review_count'    => (int) ($place['userRatingCount'] ?? 0),
            'photo_count'     => count($place['photos'] ?? []),
            'has_description' => ! empty($place['editorialSummary']['text'] ?? null),
            'hours_complete'  => ! empty($place['regularOpeningHours']['periods'] ?? null),
        ];
    }
}
```

- [ ] **Step 4: Run test — expect pass**

```bash
./vendor/bin/phpunit tests/Unit/BenchmarkNormalizerTest.php -v
```

Expected: PASS

- [ ] **Step 5: Refactor ReportBuilderService**

In `app/Services/ReportBuilderService.php`, inject or instantiate `BenchmarkNormalizer` and replace the inline `$benchmark = [...]` block (lines 24–33) with:

```php
$benchmark = $benchmarkPlace
    ? (new BenchmarkNormalizer())->fromPlace($benchmarkPlace)
    : null;
```

Keep `$comparison` gap logic unchanged (it already uses `review_count`, `photo_count`, etc.).

- [ ] **Step 6: Run ReportBuilder tests**

```bash
./vendor/bin/phpunit tests/Unit/ReportBuilderServiceTest.php -v
```

Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add app/Services/BenchmarkNormalizer.php tests/Unit/BenchmarkNormalizerTest.php app/Services/ReportBuilderService.php
git commit -m "feat: add BenchmarkNormalizer shared by reports and scoring"
```

---

### Task 3: Places API — businessStatus field mask

**Files:**
- Modify: `app/Services/GooglePlacesService.php`

- [ ] **Step 1: Add `businessStatus` to getPlaceDetails mask**

In `getPlaceDetails()`, add `'businessStatus'` to the `$fieldMask` array (after `'primaryType'`).

- [ ] **Step 2: Commit**

```bash
git add app/Services/GooglePlacesService.php
git commit -m "feat(places): request businessStatus in place details"
```

---

### Task 4: GbpScoringService — absolute flags (TDD)

**Files:**
- Modify: `app/Services/GbpScoringService.php`
- Modify: `tests/Unit/GbpScoringServiceTest.php`

- [ ] **Step 1: Add failing tests for new absolute behaviour**

Append to `tests/Unit/GbpScoringServiceTest.php`:

```php
public function test_empty_profile_includes_phone_flag_and_higher_score(): void
{
    $result = $this->service->score([]);

    $this->assertContains('No phone number listed', $result['flags']);
    $this->assertEquals(78, $result['score']); // 70 + 8 phone
}

public function test_rating_between_3_5_and_4_adds_tier_flag(): void
{
    $payload = [
        'rating' => 3.8,
        'userRatingCount' => 200,
        'photos' => array_fill(0, 20, []),
        'websiteUri' => 'https://example.com',
        'nationalPhoneNumber' => '+441234567890',
        'editorialSummary' => ['text' => 'Desc'],
        'regularOpeningHours' => ['periods' => [['open' => ['day' => 1]]]],
    ];

    $result = $this->service->score($payload);

    $this->assertContains('Rating below 4 stars', $result['flags']);
    $this->assertNotContains('Rating below 3.5 stars', $result['flags']);
}

public function test_five_to_nine_photos_flag(): void
{
    $payload = ['photos' => array_fill(0, 7, [])];
    $result = $this->service->score($payload);

    $this->assertContains('Fewer than 10 photos', $result['flags']);
    $this->assertNotContains('Fewer than 5 photos', $result['flags']);
}

public function test_social_website_host_flag(): void
{
    $payload = ['websiteUri' => 'https://www.facebook.com/my-business'];
    $result = $this->service->score($payload);

    $this->assertContains('No dedicated website', $result['flags']);
    $this->assertNotContains('No website listed', $result['flags']);
}

public function test_non_operational_business_status_flag(): void
{
    $payload = ['businessStatus' => 'CLOSED_TEMPORARILY'];
    $result = $this->service->score($payload);

    $this->assertContains('Listing not fully operational', $result['flags']);
}

public function test_strong_profile_requires_phone(): void
{
    $payload = [
        'id' => 'places/abc',
        'userRatingCount' => 200,
        'rating' => 4.5,
        'photos' => array_fill(0, 20, []),
        'websiteUri' => 'https://example.com',
        'nationalPhoneNumber' => '+441234567890',
        'editorialSummary' => ['text' => 'A great business'],
        'regularOpeningHours' => ['periods' => [['open' => ['day' => 1]]]],
    ];

    $result = $this->service->score($payload);
    $this->assertEquals(0, $result['score']);
    $this->assertEmpty($result['flags']);
}
```

Update `test_strong_profile_scores_zero` to include `nationalPhoneNumber` and `id` as above, or remove duplicate if replaced.

- [ ] **Step 2: Run tests — expect fail**

```bash
./vendor/bin/phpunit tests/Unit/GbpScoringServiceTest.php -v
```

- [ ] **Step 3: Refactor GbpScoringService — absolute layer**

Replace `app/Services/GbpScoringService.php` with layered implementation. Public API:

```php
public function score(array $payload, ?array $benchmark = null, string $city = ''): array
{
    $absolute = $this->scoreAbsolute($payload);
    $relative = ($benchmark && ($payload['id'] ?? null) !== ($benchmark['place_id'] ?? null))
        ? $this->scoreRelative($payload, $benchmark, $city)
        : ['score' => 0, 'flags' => [], 'skip_description_relative' => false, 'skip_hours_relative' => false];

    return $this->mergeScores($absolute, $relative);
}
```

Implement `scoreAbsolute` with existing rubric **plus**:

- Phone: +8 if `empty($payload['nationalPhoneNumber'])`
- Photos 5–9: +5, only if not 0 or 1–4 tiers
- Rating 3.5–4.0: +5, only if not `< 3.5` tier
- Weak website: +8 via `isWeakWebsiteHost($uri)` when URI present
- `businessStatus`: +15 if key present and value !== `'OPERATIONAL'`

Add private method:

```php
private function isWeakWebsiteHost(string $uri): bool
{
    $host = strtolower((string) parse_url($uri, PHP_URL_HOST));
    $needles = [
        'facebook.com', 'fb.com', 'instagram.com', 'linktr.ee', 'tiktok.com',
        'twitter.com', 'x.com', 'yelp.', 'wixsite.com', 'square.site',
        'godaddysites.com', 'google.site', 'sites.google.com',
    ];
    foreach ($needles as $needle) {
        if (str_contains($host, $needle)) {
            return true;
        }
    }
    return false;
}
```

`mergeScores` for Task 4 only merges absolute (relative returns empty flags):

```php
private function mergeScores(array $absolute, array $relative): array
{
    $flags = array_merge($absolute['flags'], $relative['flags'] ?? []);
    $score = min($absolute['score'] + ($relative['score'] ?? 0), 100);

    return ['score' => $score, 'flags' => $flags];
}
```

Have `scoreRelative` return `['score' => 0, 'flags' => []]` stub until Task 5.

- [ ] **Step 4: Run unit tests — expect pass**

```bash
./vendor/bin/phpunit tests/Unit/GbpScoringServiceTest.php -v
```

- [ ] **Step 5: Commit**

```bash
git add app/Services/GbpScoringService.php tests/Unit/GbpScoringServiceTest.php
git commit -m "feat(gbp): expand absolute weakness flags and layered scorer"
```

---

### Task 5: GbpScoringService — relative (benchmark) flags

**Files:**
- Modify: `app/Services/GbpScoringService.php`
- Modify: `tests/Unit/GbpScoringServiceTest.php`

- [ ] **Step 1: Add benchmark fixture helper and failing relative tests**

In `GbpScoringServiceTest.php`:

```php
private function benchmarkFixture(): array
{
    return [
        'place_id' => 'places/leader',
        'name' => 'Top Dental',
        'review_count' => 300,
        'photo_count' => 40,
        'rating' => 4.9,
        'has_description' => true,
        'hours_complete' => true,
    ];
}

public function test_relative_review_gap_flag_includes_counts_and_city(): void
{
    $payload = [
        'id' => 'places/prospect',
        'userRatingCount' => 42,
        'photos' => array_fill(0, 10, []),
        'websiteUri' => 'https://example.com',
        'nationalPhoneNumber' => '+441234',
        'editorialSummary' => ['text' => 'x'],
        'regularOpeningHours' => ['periods' => [['open' => ['day' => 1]]]],
        'rating' => 4.5,
    ];

    $result = $this->service->score($payload, $this->benchmarkFixture(), 'Birmingham');

    $this->assertContains('42 reviews vs 300 for the top listing in Birmingham', $result['flags']);
}

public function test_skips_relative_flags_when_prospect_is_leader(): void
{
    $payload = ['id' => 'places/leader', 'userRatingCount' => 300];
    $result = $this->service->score($payload, $this->benchmarkFixture(), 'Birmingham');

    $this->assertNotContains('42 reviews vs 300 for the top listing in Birmingham', $result['flags']);
}

public function test_does_not_double_description_flag(): void
{
    $payload = ['id' => 'places/p1', 'userRatingCount' => 5];
    $result = $this->service->score($payload, $this->benchmarkFixture(), 'Leeds');

    $this->assertContains('Missing business description', $result['flags']);
    $this->assertNotContains('No description while top listing in Leeds has one', $result['flags']);
}

public function test_score_capped_at_100(): void
{
    $result = $this->service->score([], $this->benchmarkFixture(), 'Birmingham');
    $this->assertLessThanOrEqual(100, $result['score']);
}
```

- [ ] **Step 2: Run tests — expect fail on relative assertions**

```bash
./vendor/bin/phpunit tests/Unit/GbpScoringServiceTest.php -v
```

- [ ] **Step 3: Implement scoreRelative and merge exclusion**

`scoreRelative` extracts prospect metrics (same as `extractFields` logic inline or call `extractFields`):

```php
private function scoreRelative(array $payload, array $benchmark, string $city): array
{
    $fields = $this->extractFields($payload);
    $score = 0;
    $flags = [];
    $skipDescription = false;
    $skipHours = false;

    $prospectReviews = $fields['review_count'];
    $leaderReviews = (int) $benchmark['review_count'];

    if ($leaderReviews >= 20) {
        $gap = max(0, $leaderReviews - $prospectReviews);
        $ratio = $leaderReviews > 0 ? $prospectReviews / $leaderReviews : 1;
        if ($gap >= 25 || $prospectReviews < 0.5 * $leaderReviews) {
            $score += 15;
            $flags[] = "{$prospectReviews} reviews vs {$leaderReviews} for the top listing in {$city}";
        }
    }

    $photoGap = max(0, (int) $benchmark['photo_count'] - $fields['photo_count']);
    if ($photoGap >= 5) {
        $score += 10;
        $flags[] = "Fewer photos than top local listing ({$fields['photo_count']} vs {$benchmark['photo_count']})";
    }

    if (! $fields['has_description'] && ($benchmark['has_description'] ?? false)) {
        $score += 8;
        $flags[] = "No description while top listing in {$city} has one";
    } else {
        $skipDescription = ! $fields['has_description'];
    }

    if (! $fields['hours_complete'] && ($benchmark['hours_complete'] ?? false)) {
        $score += 8;
        $flags[] = "Hours incomplete vs top listing in {$city}";
    } else {
        $skipHours = ! $fields['hours_complete'];
    }

    if ($fields['rating'] !== null && $benchmark['rating'] !== null) {
        $gap = (float) $benchmark['rating'] - (float) $fields['rating'];
        if ($gap >= 0.3) {
            $score += 8;
            $flags[] = sprintf(
                'Lower rating than top listing in %s (%s vs %s)',
                $city,
                number_format((float) $fields['rating'], 1),
                number_format((float) $benchmark['rating'], 1),
            );
        }
    }

    return [
        'score' => $score,
        'flags' => $flags,
        'absolute_skip_description' => $skipDescription && ! $fields['has_description'],
        'absolute_skip_hours' => $skipHours && ! $fields['hours_complete'],
    ];
}
```

Update `mergeScores` to drop relative description/hours flags when absolute already flagged:

```php
private function mergeScores(array $absolute, array $relative): array
{
    $absoluteFlags = $absolute['flags'];
    $relativeFlags = $relative['flags'];

    if (in_array('Missing business description', $absoluteFlags, true)) {
        $relativeFlags = array_values(array_filter(
            $relativeFlags,
            fn (string $f) => ! str_starts_with($f, 'No description while top listing')
        ));
    }
    if (in_array('Opening hours not set', $absoluteFlags, true)) {
        $relativeFlags = array_values(array_filter(
            $relativeFlags,
            fn (string $f) => ! str_starts_with($f, 'Hours incomplete vs top listing')
        ));
    }

    $relativeScore = $relative['score'];
    // Recompute relative score if flags removed (optional: track points per flag; simpler: recalc from kept flags or store points in merge — for tests, subtract 8 when filtering desc/hours)
    // Simplest approach: build relative with score only from flags that survive filter — refactor scoreRelative to return flag=>points map OR recalculate score from filtered flags by storing score adjustments in merge.

    $flags = array_merge($absoluteFlags, $relativeFlags);
    $score = min($absolute['score'] + $relativeScore, 100);

    return ['score' => $score, 'flags' => $flags];
}
```

**Important:** When filtering duplicate description/hours relative flags, subtract 8 from `$relativeScore` for each removed flag so score stays consistent.

- [ ] **Step 4: Run all GbpScoringService tests**

```bash
./vendor/bin/phpunit tests/Unit/GbpScoringServiceTest.php -v
```

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/GbpScoringService.php tests/Unit/GbpScoringServiceTest.php
git commit -m "feat(gbp): add benchmark-relative weakness flags"
```

---

### Task 6: Wire jobs to benchmark snapshot

**Files:**
- Modify: `app/Jobs/ScrapeProspectsJob.php`
- Modify: `app/Jobs/ScorePlaceJob.php`

- [ ] **Step 1: Update ScrapeProspectsJob**

```php
use App\Services\BenchmarkNormalizer;
use Illuminate\Support\Facades\Log;

// Inside handle(), after $placeIds = $places->searchByNicheAndCity(...):

$benchmarkPlace = $places->getTopRankedInNiche(
    $this->search->niche,
    $this->search->city,
    $this->search->country,
);

if (! $benchmarkPlace) {
    Log::warning('ScrapeProspectsJob: no benchmark place returned', [
        'search_id' => $this->search->id,
    ]);
}

$this->search->update([
    'total_found' => count($placeIds),
    'benchmark_snapshot' => $benchmarkPlace
        ? (new BenchmarkNormalizer())->fromPlace($benchmarkPlace)
        : null,
]);
```

Remove duplicate `total_found` update if it existed separately — single `update` call.

- [ ] **Step 2: Update ScorePlaceJob**

After `$search = $this->search->fresh();`:

```php
$scored = $scorer->score(
    $payload,
    $search->benchmark_snapshot,
    $search->city,
);
```

- [ ] **Step 3: Commit**

```bash
git add app/Jobs/ScrapeProspectsJob.php app/Jobs/ScorePlaceJob.php
git commit -m "feat(jobs): cache benchmark snapshot and pass to GBP scorer"
```

---

### Task 7: Feature test — ScrapeProspectsJob persists benchmark

**Files:**
- Create: `tests/Feature/ScrapeProspectsJobTest.php`

- [ ] **Step 1: Write feature test**

```php
<?php

namespace Tests\Feature;

use App\Jobs\ScrapeProspectsJob;
use App\Models\Search;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ScrapeProspectsJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_persists_benchmark_snapshot_when_places_returns_leader(): void
    {
        config(['services.google_places.key' => 'test-key']);

        Http::fake([
            'https://places.googleapis.com/v1/places:searchText' => Http::sequence()
                ->push(['places' => [['id' => 'places/prospect1']]], 200)
                ->push([
                    'places' => [[
                        'id' => 'places/leader',
                        'displayName' => ['text' => 'Leader Co'],
                        'userRatingCount' => 200,
                        'photos' => array_fill(0, 10, []),
                        'rating' => 4.8,
                        'editorialSummary' => ['text' => 'Best'],
                        'regularOpeningHours' => ['periods' => [['open' => ['day' => 1]]]],
                    ]],
                ], 200),
            'https://places.googleapis.com/v1/places/places/*' => Http::response([], 200),
        ]);

        $search = Search::factory()->create(['status' => 'pending', 'total_found' => null]);

        (new ScrapeProspectsJob($search))->handle(
            app(\App\Services\GooglePlacesService::class),
            app(\App\Services\SearchStatusService::class),
        );

        $search->refresh();
        $this->assertSame('places/leader', $search->benchmark_snapshot['place_id']);
        $this->assertSame(200, $search->benchmark_snapshot['review_count']);
    }
}
```

Adjust fake URLs if `searchByNicheAndCity` / `getTopRankedInNiche` paths differ (both use `:searchText` POST — sequence order: first call discovery, second call benchmark).

- [ ] **Step 2: Run feature test**

```bash
./vendor/bin/phpunit tests/Feature/ScrapeProspectsJobTest.php -v
```

Fix HTTP sequence ordering if test fails (log request order).

- [ ] **Step 3: Run full test suite**

```bash
./vendor/bin/phpunit -v
```

Expected: all PASS

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/ScrapeProspectsJobTest.php
git commit -m "test: assert ScrapeProspectsJob stores benchmark_snapshot"
```

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| `benchmark_snapshot` column | Task 1 |
| `BenchmarkNormalizer` shared with reports | Task 2 |
| `businessStatus` in field mask | Task 3 |
| Absolute rubric + mutual exclusion | Task 4 |
| Relative rubric + leader skip + desc/hours dedup | Task 5 |
| `ScrapeProspectsJob` benchmark fetch | Task 6 |
| `ScorePlaceJob` passes benchmark + city | Task 6 |
| Unit tests | Tasks 4–5 |
| Feature test | Task 7 |
| Review recency deferred | N/A |
| `CombineScoresService` unchanged | N/A |

---

## Manual smoke test (post-implementation)

1. Run a `gbp_only` search for a known niche/city in local/staging.
2. Open search results — confirm `gbp_score` spread and new flags on weak listings.
3. Open a prospect with benchmark gap — confirm flag like `N reviews vs M for the top listing in {city}`.
4. Generate outreach — confirm `Key issues` line includes new flags.

---

## Execution handoff

Plan complete and saved to `docs/superpowers/plans/2026-05-27-gbp-scoring-flags.md`.

**Two execution options:**

1. **Subagent-Driven (recommended)** — fresh subagent per task, review between tasks  
2. **Inline Execution** — implement in this session with checkpoints  

Which approach do you want?
