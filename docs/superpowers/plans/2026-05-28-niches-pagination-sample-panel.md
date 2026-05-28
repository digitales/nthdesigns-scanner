# Niches Pagination & Sample Panel — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Paginate `/niches` with infinite scroll and URL page state, sticky list chrome, and a side panel showing sampled business names with on-demand backfill.

**Architecture:** Add `sample_preview` JSON via migration; extract `NicheSampleCollector` from `ScanNicheJob`; paginate the existing latest-per-(niche, city) query in `NicheScanController`; add JSON `NicheScanSampleController` for panel data/backfill; rebuild `Niches/Index.jsx` with client row merge, `IntersectionObserver`, and `NicheSamplePanel`.

**Tech Stack:** Laravel 13, Inertia + React, PHPUnit, `Http::fake()` for Places, existing UI kit (`DataTable`, `ScoreBadge`, `PageHeader`).

**Spec:** `docs/superpowers/specs/2026-05-28-niches-pagination-sample-panel-design.md`

---

## File map

| File | Responsibility |
|------|----------------|
| `database/migrations/2026_05_28_*_add_sample_preview_to_niche_scans_table.php` | JSON column |
| `app/Models/NicheScan.php` | `sample_preview` fillable + array cast |
| `app/Services/NicheSampleCollector.php` | Places sample loop → metrics + preview items |
| `app/Jobs/ScanNicheJob.php` | Delegate to collector; persist `sample_preview` |
| `app/Http/Controllers/NicheScanController.php` | `paginate(50)` + `id` + `pagination` prop |
| `app/Http/Controllers/NicheScanSampleController.php` | JSON show / 202 backfill |
| `routes/web.php` | `GET /niches/{nicheScan}/sample` |
| `resources/js/Pages/Niches/Index.jsx` | Sticky layout, infinite scroll, selection |
| `resources/js/Components/Niches/NicheSamplePanel.jsx` | Panel UI + fetch/poll |
| `resources/css/components.css` | `.niches-*` layout styles |
| `tests/Feature/NicheScanControllerTest.php` | Pagination + filters |
| `tests/Feature/NicheScanSampleControllerTest.php` | 200 / 202 / dispatch |
| `tests/Feature/ScanNicheJobTest.php` | Assert `sample_preview` persisted |

---

### Task 1: `sample_preview` migration and model

**Files:**
- Create: `database/migrations/2026_05_28_100000_add_sample_preview_to_niche_scans_table.php`
- Modify: `app/Models/NicheScan.php`

- [ ] **Step 1: Write migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('niche_scans', function (Blueprint $table) {
            $table->json('sample_preview')->nullable()->after('sampled_count');
        });
    }

    public function down(): void
    {
        Schema::table('niche_scans', function (Blueprint $table) {
            $table->dropColumn('sample_preview');
        });
    }
};
```

- [ ] **Step 2: Run migration**

```bash
php artisan migrate
```

Expected: `sample_preview` column on `niche_scans`.

- [ ] **Step 3: Update model**

Add to `$fillable`: `'sample_preview'`.

Add to `casts()`:

```php
'sample_preview' => 'array',
```

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_05_28_100000_add_sample_preview_to_niche_scans_table.php app/Models/NicheScan.php
git commit -m "feat(niches): add sample_preview column to niche_scans"
```

---

### Task 2: `NicheSampleCollector` and job persistence

**Files:**
- Create: `app/Services/NicheSampleCollector.php`
- Modify: `app/Jobs/ScanNicheJob.php`
- Modify: `tests/Feature/ScanNicheJobTest.php`

- [ ] **Step 1: Write failing test for `sample_preview`**

Add to `tests/Feature/ScanNicheJobTest.php` inside `test_completes_scan_with_aggregates_and_opportunity_score`:

```php
$this->assertIsArray($row->sample_preview);
$this->assertCount(2, $row->sample_preview);
$this->assertSame('A', $row->sample_preview[0]['name']);
$this->assertArrayHasKey('gbp_score', $row->sample_preview[0]);
$this->assertTrue($row->sample_preview[0]['no_website']);
```

Add to `test_zero_results_completes_with_opportunity_score_zero`:

```php
$this->assertSame([], $row->sample_preview);
```

- [ ] **Step 2: Run test to verify failure**

```bash
php artisan test --filter=ScanNicheJobTest
```

Expected: FAIL (missing `sample_preview` or wrong shape).

- [ ] **Step 3: Create `NicheSampleCollector`**

```php
<?php

namespace App\Services;

use Illuminate\Support\Arr;

final class NicheSampleCollector
{
    public function __construct(
        private GooglePlacesService $places,
        private GbpScoringService $scorer,
    ) {}

    /**
     * @return array{
     *     result_count: int,
     *     sampled_count: int,
     *     avg_gbp_score: float,
     *     pct_no_website: float,
     *     pct_low_reviews: float,
     *     opportunity_score: float,
     *     sample_preview: array<int, array{name: string, gbp_score: int, no_website: bool, review_count: int}>
     * }
     */
    public function collect(string $nicheQuery, string $city, string $country, int $sample): array
    {
        $placeIds = $this->places->searchByNicheAndCity($nicheQuery, $city, $country);
        $resultCount = count($placeIds);

        if ($resultCount === 0) {
            return [
                'result_count' => 0,
                'sampled_count' => 0,
                'avg_gbp_score' => 0,
                'pct_no_website' => 0,
                'pct_low_reviews' => 0,
                'opportunity_score' => 0,
                'sample_preview' => [],
            ];
        }

        $sampleSize = min($sample, $resultCount);
        $sampleIds = Arr::random($placeIds, $sampleSize);
        $sampleIds = is_array($sampleIds) ? $sampleIds : [$sampleIds];

        $scores = [];
        $preview = [];
        $noWebsite = 0;
        $lowReviews = 0;
        $sampled = 0;

        foreach ($sampleIds as $placeId) {
            $payload = $this->places->getPlaceDetails($placeId);

            if (! $payload) {
                continue;
            }

            $sampled++;
            $scored = $this->scorer->score($payload, null);
            $scores[] = $scored['score'];

            $reviewCount = (int) ($payload['userRatingCount'] ?? 0);
            $hasNoWebsite = empty($payload['websiteUri']);

            if ($hasNoWebsite) {
                $noWebsite++;
            }

            if ($reviewCount < 20) {
                $lowReviews++;
            }

            $preview[] = [
                'name' => $payload['displayName']['text'] ?? 'Unknown',
                'gbp_score' => (int) round($scored['score']),
                'no_website' => $hasNoWebsite,
                'review_count' => $reviewCount,
            ];
        }

        if ($sampled === 0) {
            return [
                'result_count' => $resultCount,
                'sampled_count' => 0,
                'avg_gbp_score' => 0,
                'pct_no_website' => 0,
                'pct_low_reviews' => 0,
                'opportunity_score' => 0,
                'sample_preview' => [],
            ];
        }

        $avg = array_sum($scores) / $sampled;
        $pctNoWebsite = ($noWebsite / $sampled) * 100;
        $pctLowReviews = ($lowReviews / $sampled) * 100;

        return [
            'result_count' => $resultCount,
            'sampled_count' => $sampled,
            'avg_gbp_score' => round($avg, 2),
            'pct_no_website' => round($pctNoWebsite, 2),
            'pct_low_reviews' => round($pctLowReviews, 2),
            'opportunity_score' => ScanNicheJob::opportunityScore($avg, $pctNoWebsite, $pctLowReviews),
            'sample_preview' => $preview,
        ];
    }
}
```

- [ ] **Step 4: Refactor `ScanNicheJob::handle`**

Replace the inline sampling loop with:

```php
public function handle(NicheSampleCollector $collector): void
{
    $scan = $this->pendingScan();

    $result = $collector->collect(
        $this->nicheQuery,
        $this->city,
        $this->country,
        $this->sample,
    );

    $this->markComplete($scan, $result);
}
```

Update `markComplete` to accept `sample_preview` in the metrics array and persist it:

```php
$scan->update([
    ...$metrics,
    'status' => 'complete',
    'ran_at' => now(),
]);
```

Remove unused constructor injections of `GooglePlacesService` / `GbpScoringService` from `handle` signature (keep class properties if `failed()` still needs nothing from them).

Update `ScanNicheJobTest` `handle()` calls to:

```php
)->handle(app(\App\Services\NicheSampleCollector::class));
```

- [ ] **Step 5: Run tests**

```bash
php artisan test --filter=ScanNicheJobTest
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Services/NicheSampleCollector.php app/Jobs/ScanNicheJob.php tests/Feature/ScanNicheJobTest.php
git commit -m "feat(niches): persist sample_preview via NicheSampleCollector"
```

---

### Task 3: Paginated index controller

**Files:**
- Modify: `app/Http/Controllers/NicheScanController.php`
- Modify: `tests/Feature/NicheScanControllerTest.php`

- [ ] **Step 1: Write failing pagination test**

Add to `tests/Feature/NicheScanControllerTest.php`:

```php
public function test_index_paginates_latest_scan_per_niche_city(): void
{
    $user = User::factory()->create();

    for ($i = 0; $i < 60; $i++) {
        NicheScan::query()->create([
            'niche' => "Niche {$i}",
            'niche_query' => "niche {$i}",
            'city' => 'Leeds',
            'country' => 'GB',
            'scan_date' => '2026-05-27',
            'result_count' => 10,
            'sampled_count' => 5,
            'avg_gbp_score' => 50,
            'pct_no_website' => 20,
            'pct_low_reviews' => 40,
            'opportunity_score' => 45 + $i,
            'status' => 'complete',
            'ran_at' => now()->subMinutes(60 - $i),
        ]);
    }

    $this->actingAs($user)
        ->get('/niches')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Niches/Index')
            ->has('scans', 50)
            ->where('pagination.total', 60)
            ->where('pagination.current_page', 1)
            ->where('pagination.per_page', 50)
            ->where('pagination.last_page', 2)
            ->where('scans.0.id', fn ($id) => $id !== null)
        );
}
```

Add filter test (optional same file):

```php
public function test_index_filters_by_city(): void
{
    $user = User::factory()->create();
    // create one Leeds + one Manchester row (complete, distinct niche labels)
    // GET /niches?city=Leeds → has scans 1, pagination.total 1
}
```

- [ ] **Step 2: Run test to verify failure**

```bash
php artisan test --filter=NicheScanControllerTest::test_index_paginates
```

Expected: FAIL (no `pagination` prop or wrong scan count).

- [ ] **Step 3: Implement pagination in controller**

Extract row mapper to a private method; add `id` field:

```php
private function mapScan(NicheScan $s): array
{
    return [
        'id' => $s->id,
        'niche' => $s->niche,
        // ... existing fields unchanged
    ];
}
```

Replace `->get()->map(...)` with:

```php
$paginator = $baseQuery->paginate(50)->withQueryString();

return Inertia::render('Niches/Index', [
    'scans' => $paginator->getCollection()->map(fn (NicheScan $s) => $this->mapScan($s))->values(),
    'pagination' => [
        'total' => $paginator->total(),
        'current_page' => $paginator->currentPage(),
        'per_page' => $paginator->perPage(),
        'last_page' => $paginator->lastPage(),
    ],
    'cities' => ...,
    'filters' => [
        'city' => ...,
        'sort' => $sortColumn,
        'page' => $paginator->currentPage(),
    ],
]);
```

- [ ] **Step 4: Run tests**

```bash
php artisan test --filter=NicheScanControllerTest
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/NicheScanController.php tests/Feature/NicheScanControllerTest.php
git commit -m "feat(niches): paginate index at 50 rows per page"
```

---

### Task 4: Sample JSON endpoint and backfill

**Files:**
- Create: `app/Http/Controllers/NicheScanSampleController.php`
- Create: `tests/Feature/NicheScanSampleControllerTest.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Write failing tests**

```php
<?php

namespace Tests\Feature;

use App\Jobs\ScanNicheJob;
use App\Models\NicheScan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NicheScanSampleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_sample_when_present(): void
    {
        $user = User::factory()->create();
        $scan = NicheScan::factory()->create(); // or manual create with sample_preview

        $scan = NicheScan::query()->create([
            'niche' => 'Dental Practice',
            'niche_query' => 'dental practice',
            'city' => 'Leeds',
            'country' => 'GB',
            'scan_date' => '2026-05-27',
            'result_count' => 10,
            'sampled_count' => 1,
            'status' => 'complete',
            'ran_at' => now(),
            'sample_preview' => [
                ['name' => 'Joe\'s Dental', 'gbp_score' => 72, 'no_website' => true, 'review_count' => 5],
            ],
            // required decimal fields per schema — copy from NicheScanControllerTest
        ]);

        $this->actingAs($user)
            ->getJson("/niches/{$scan->id}/sample")
            ->assertOk()
            ->assertJsonPath('status', 'ready')
            ->assertJsonPath('items.0.name', 'Joe\'s Dental');
    }

    public function test_show_dispatches_job_when_preview_missing(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $scan = NicheScan::query()->create([/* complete row, sample_preview null */]);

        $this->actingAs($user)
            ->getJson("/niches/{$scan->id}/sample")
            ->assertStatus(202)
            ->assertJsonPath('status', 'loading');

        Queue::assertPushed(ScanNicheJob::class, fn ($job) => $job->niche === $scan->niche && $job->city === $scan->city);
    }

    public function test_show_returns_loading_when_pending_without_dispatch(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $scan = NicheScan::query()->create([/* status pending, sample_preview null */]);

        $this->actingAs($user)
            ->getJson("/niches/{$scan->id}/sample")
            ->assertStatus(202)
            ->assertJsonPath('status', 'loading');

        Queue::assertNothingPushed();
    }
}
```

Fill required nullable decimals on create to satisfy DB.

- [ ] **Step 2: Run tests — expect FAIL**

```bash
php artisan test --filter=NicheScanSampleControllerTest
```

- [ ] **Step 3: Implement controller**

```php
<?php

namespace App\Http\Controllers;

use App\Jobs\ScanNicheJob;
use App\Models\NicheScan;
use App\Support\ScrapingQueue;
use Illuminate\Http\JsonResponse;

class NicheScanSampleController extends Controller
{
    public function show(NicheScan $nicheScan): JsonResponse
    {
        if ($nicheScan->status === 'failed') {
            return response()->json(['status' => 'failed', 'message' => 'Sample scan failed.'], 422);
        }

        if ($nicheScan->sample_preview !== null) {
            return response()->json([
                'status' => 'ready',
                'niche' => $nicheScan->niche,
                'city' => $nicheScan->city,
                'country' => $nicheScan->country,
                'niche_query' => $nicheScan->niche_query,
                'sampled_count' => $nicheScan->sampled_count,
                'result_count' => $nicheScan->result_count,
                'opportunity_score' => $nicheScan->opportunity_score,
                'ran_at_human' => $nicheScan->ran_at?->diffForHumans() ?? '—',
                'items' => $nicheScan->sample_preview,
            ]);
        }

        if ($nicheScan->status !== 'pending') {
            ScrapingQueue::dispatch(new ScanNicheJob(
                niche: $nicheScan->niche,
                nicheQuery: $nicheScan->niche_query,
                city: $nicheScan->city,
                country: $nicheScan->country,
                sample: (int) config('niches.sample_size', 5),
                scanDate: now('Europe/London')->toDateString(),
            ));
        }

        return response()->json(['status' => 'loading'], 202);
    }
}
```

Add to `config/niches.php` (top-level key):

```php
'sample_size' => 5,
```

- [ ] **Step 4: Register route** (inside `auth` middleware group in `routes/web.php`)

```php
Route::get('/niches/{nicheScan}/sample', [NicheScanSampleController::class, 'show'])->name('niches.sample');
```

- [ ] **Step 5: Run tests — expect PASS**

```bash
php artisan test --filter=NicheScanSampleControllerTest
```

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/NicheScanSampleController.php tests/Feature/NicheScanSampleControllerTest.php routes/web.php config/niches.php
git commit -m "feat(niches): sample JSON endpoint with on-demand backfill"
```

---

### Task 5: Niches layout CSS

**Files:**
- Modify: `resources/css/components.css`

- [ ] **Step 1: Add styles** (append in `@layer components` or equivalent)

```css
.niches-layout {
  display: flex;
  align-items: flex-start;
  gap: 0;
  min-height: 0;
}

.niches-main {
  flex: 1;
  min-width: 0;
}

.niches-sticky-stack {
  position: sticky;
  top: 52px;
  z-index: 40;
  background: var(--color-paper);
  border-bottom: 1px solid var(--color-line);
}

.niches-list-meta {
  padding: 10px 20px;
  font-family: var(--font-mono);
  font-size: 11px;
  color: var(--color-stone-500);
}

.niches-table-scroll {
  max-height: calc(100vh - 52px - 280px);
  overflow-y: auto;
}

.niches-panel {
  width: 360px;
  flex-shrink: 0;
  border-left: 1px solid var(--color-line);
  background: var(--color-paper);
  max-height: calc(100vh - 52px);
  overflow-y: auto;
  position: sticky;
  top: 52px;
}

@media (max-width: 1023px) {
  .niches-layout {
    flex-direction: column;
  }
  .niches-panel {
    position: fixed;
    inset: 52px 0 0 0;
    width: 100%;
    z-index: 45;
    max-height: none;
  }
}
```

Tune `280px` after assembling sticky stack height in Task 6.

- [ ] **Step 2: Run build**

```bash
npm run build
```

Expected: no CSS errors.

- [ ] **Step 3: Commit**

```bash
git add resources/css/components.css
git commit -m "style(niches): layout and sticky stack CSS"
```

---

### Task 6: `NicheSamplePanel` component

**Files:**
- Create: `resources/js/Components/Niches/NicheSamplePanel.jsx`

- [ ] **Step 1: Create panel component**

Props: `{ scan, onClose, onRunFullScan }` where `scan` is the selected table row (`id`, `niche`, `city`, `country`, `niche_query`, `opportunity_score`, etc.).

Behaviour:

- `useEffect` on `scan.id`: `fetch(`/niches/${scan.id}/sample`, { headers: { Accept: 'application/json' } })`
- `202` → poll every 2000ms (max 30 attempts); abort on unmount or `scan.id` change via `AbortController`
- `200` → set `items`, `sampled_count`, `result_count`, `ran_at_human`
- `422` / failed JSON → error state + Retry button (re-run fetch)
- Empty `items` → “No places found in this market”
- Render list with `ScoreBadge`; chips for `no_website` and `review_count < 20`
- Footer: `Run Full Scan` calls `onRunFullScan(scan)`

Use existing `Button`, `ScoreBadge`, `Icons` from `@/Components/ui`.

- [ ] **Step 2: Manual smoke** (after Task 7 wires it)

Open `/niches`, click row with `sample_preview` in DB → names appear.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Components/Niches/NicheSamplePanel.jsx
git commit -m "feat(niches): sample side panel with fetch and poll"
```

---

### Task 7: `Niches/Index.jsx` — infinite scroll and sticky chrome

**Files:**
- Modify: `resources/js/Pages/Niches/Index.jsx`

- [ ] **Step 1: Add state and helpers**

```jsx
const PER_PAGE = 50;

export default function NichesIndex({ scans: initialScans, pagination, cities, filters }) {
    const [rows, setRows] = useState(initialScans);
    const [meta, setMeta] = useState(pagination);
    const [loadingMore, setLoadingMore] = useState(false);
    const [selected, setSelected] = useState(null);
    const sentinelRef = useRef(null);

    // Reset when Inertia replaces props (filter change)
    useEffect(() => {
        setRows(initialScans);
        setMeta(pagination);
        setSelected(null);
    }, [initialScans, pagination]);

    const loadedCount = rows.length;
    const from = loadedCount === 0 ? 0 : 1;
    const to = loadedCount;
    const currentPage = meta?.current_page ?? 1;
```

- [ ] **Step 2: Deep-link hydration** (`useEffect` on mount)

Read `page` from `new URLSearchParams(window.location.search).get('page')` (or `filters.page`).

If `page > 1`, sequentially `router.get` pages `2..page` with `only: ['scans', 'pagination']`, merging each `scans` into `rows` (guard duplicate ids). After merge, `scrollIntoView` row at index `(page - 1) * PER_PAGE`.

- [ ] **Step 3: Infinite scroll observer**

```jsx
useEffect(() => {
    const el = sentinelRef.current;
    if (!el || loadingMore || currentPage >= meta.last_page) return;

    const observer = new IntersectionObserver((entries) => {
        if (!entries[0]?.isIntersecting) return;
        loadPage(meta.current_page + 1);
    }, { rootMargin: '200px' });

    observer.observe(el);
    return () => observer.disconnect();
}, [loadingMore, meta, rows]);
```

`loadPage` calls:

```jsx
router.get('/niches', { city: filters.city ?? '', sort: filters.sort, page: nextPage }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
    only: ['scans', 'pagination'],
    onStart: () => setLoadingMore(true),
    onFinish: () => setLoadingMore(false),
    onSuccess: ({ props }) => {
        setRows((prev) => {
            const ids = new Set(prev.map((r) => r.id));
            const merged = [...prev];
            for (const row of props.scans) {
                if (!ids.has(row.id)) merged.push(row);
            }
            return merged;
        });
        setMeta(props.pagination);
    },
});
```

- [ ] **Step 4: Restructure JSX**

Wrap in `div.niches-layout`:

- Left `div.niches-main`:
  - `div.niches-sticky-stack`: `PageHeader`, `FilterBar`, `div.niches-list-meta` with copy `Showing {from}–{to} of {meta.total} · Page {currentPage} of {meta.last_page}`
  - `DataTable` with `thead` inside sticky stack
  - `div.niches-table-scroll` containing `tbody`, sentinel `<tr ref={sentinelRef}><td colSpan={9} /></tr>`, loading row when `loadingMore`
- Right: `{selected && <NicheSamplePanel scan={selected} onClose={() => setSelected(null)} onRunFullScan={runFullScan} />}`

Row `onClick` → `setSelected(row)`; `className` add `selected` when `selected?.id === row.id`.

`applyFilters`: include `page: 1` in params; close panel.

Run Full Scan button: `e.stopPropagation()`.

- [ ] **Step 5: Build frontend**

```bash
npm run build
```

- [ ] **Step 6: Run full test suite**

```bash
php artisan test
```

Expected: all PASS.

- [ ] **Step 7: Commit**

```bash
git add resources/js/Pages/Niches/Index.jsx
git commit -m "feat(niches): infinite scroll, sticky chrome, and sample panel"
```

---

### Task 8: Update spec status (docs only)

**Files:**
- Modify: `docs/superpowers/specs/2026-05-28-niches-pagination-sample-panel-design.md`

- [ ] **Step 1: Set status line**

```markdown
**Status:** Implemented — plan at `docs/superpowers/plans/2026-05-28-niches-pagination-sample-panel.md`
```

- [ ] **Step 2: Commit**

```bash
git add docs/superpowers/specs/2026-05-28-niches-pagination-sample-panel-design.md
git commit -m "docs: mark niches pagination spec as implemented"
```

---

## Spec coverage checklist (self-review)

| Spec requirement | Task |
|------------------|------|
| `sample_preview` JSON column | Task 1 |
| `NicheSampleCollector` + job persist | Task 2 |
| Paginate 50, `id` on rows, `pagination` prop | Task 3 |
| Sample endpoint 200/202, backfill dispatch | Task 4 |
| Sticky stack + layout CSS | Task 5, 7 |
| ListMetaBar from loaded count | Task 7 |
| Infinite scroll + URL `page` | Task 7 |
| Deep link pages 1..N | Task 7 |
| Side panel + poll | Task 6, 7 |
| Mobile overlay panel | Task 5 |
| Row select / stopPropagation on action | Task 7 |
| Filter resets page + panel | Task 7 |
| Tests per spec | Tasks 2–4 |

---

## Manual test plan

1. Seed 60+ `niche_scans` (or use bootstrap data) → `/niches` loads 50 rows, meta shows total.
2. Scroll to bottom → page 2 appends; URL shows `?page=2`; meta shows `1–100`.
3. Open `/niches?page=3` → rows 1–150 loaded; scroll position near row 101.
4. Change city filter → resets to page 1, panel closes.
5. Click row without `sample_preview` → panel loading → names appear after job runs (run queue worker locally).
6. Narrow viewport → panel covers table; close restores table.
