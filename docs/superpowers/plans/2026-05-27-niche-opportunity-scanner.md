# Niche Opportunity Scanner — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add batch niche×city GBP sampling with stored opportunity scores and a `/niches` Inertia dashboard to triage markets before full prospect scans.

**Architecture:** `niches:scan` fans out `ScanNicheJob` on `ScrapingQueue`; each job upserts `niche_scans` for `(niche, city, scan_date)` in Europe/London, samples Place Details, scores with absolute `GbpScoringService` only, and writes aggregates. UI reads latest row per niche+city and links to `gbp_only` searches.

**Tech Stack:** Laravel 13, Horizon/database `scraping` queue, Places API (New), Inertia + React, PHPUnit, Tailwind v4 UI kit.

**Spec:** `docs/superpowers/specs/2026-05-27-niche-opportunity-scanner-design.md`

---

## File map

| File | Responsibility |
|------|----------------|
| `database/migrations/2026_05_27_140000_create_niche_scans_table.php` | `niche_scans` schema + unique index |
| `config/niches.php` | 20 niche label/query pairs |
| `app/Models/NicheScan.php` | Eloquent model |
| `app/Jobs/ScanNicheJob.php` | Places fetch, score, aggregate, upsert |
| `app/Console/Commands/ScanNichesCommand.php` | `niches:scan` fan-out |
| `app/Http/Controllers/NicheScanController.php` | Index + trigger |
| `routes/web.php` | `/niches` routes |
| `routes/console.php` | Weekly schedule |
| `resources/js/Pages/Niches/Index.jsx` | Dashboard table + toolbar |
| `resources/js/Components/ui/AppShell.jsx` | Nav link |
| `tests/Feature/ScanNicheJobTest.php` | Job + HTTP fake |
| `tests/Feature/ScanNichesCommandTest.php` | Dispatch count |
| `tests/Feature/NicheScanControllerTest.php` | Inertia index + trigger |

---

### Task 1: Migration, model, and config

**Files:**
- Create: `database/migrations/2026_05_27_140000_create_niche_scans_table.php`
- Create: `app/Models/NicheScan.php`
- Create: `config/niches.php`

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
        Schema::create('niche_scans', function (Blueprint $table) {
            $table->id();
            $table->string('niche');
            $table->string('niche_query');
            $table->string('city');
            $table->string('country', 2)->default('GB');
            $table->date('scan_date');
            $table->unsignedInteger('result_count')->default(0);
            $table->unsignedInteger('sampled_count')->default(0);
            $table->decimal('avg_gbp_score', 5, 2)->nullable();
            $table->decimal('pct_no_website', 5, 2)->nullable();
            $table->decimal('pct_low_reviews', 5, 2)->nullable();
            $table->decimal('opportunity_score', 5, 2)->nullable();
            $table->enum('status', ['pending', 'complete', 'failed'])->default('pending');
            $table->timestamp('ran_at')->nullable();
            $table->timestamps();

            $table->unique(['niche', 'city', 'scan_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('niche_scans');
    }
};
```

- [ ] **Step 2: Run migration**

```bash
php artisan migrate
```

Expected: `niche_scans` table exists.

- [ ] **Step 3: Create `config/niches.php`**

```php
<?php

return [
    ['label' => 'Dental Practice', 'query' => 'dental practice'],
    ['label' => 'Physiotherapist', 'query' => 'physiotherapist'],
    ['label' => 'Solicitor', 'query' => 'solicitor'],
    ['label' => 'Accountant', 'query' => 'accountant'],
    ['label' => 'Estate Agent', 'query' => 'estate agent'],
    ['label' => 'Independent Hotel', 'query' => 'independent hotel'],
    ['label' => 'Restaurant', 'query' => 'restaurant'],
    ['label' => 'Optician', 'query' => 'optician'],
    ['label' => 'Veterinary Practice', 'query' => 'veterinary practice'],
    ['label' => 'Private GP', 'query' => 'private gp'],
    ['label' => 'Osteopath', 'query' => 'osteopath'],
    ['label' => 'Chiropractor', 'query' => 'chiropractor'],
    ['label' => 'Beauty Salon', 'query' => 'beauty salon'],
    ['label' => 'Barbershop', 'query' => 'barbershop'],
    ['label' => 'Plumber', 'query' => 'plumber'],
    ['label' => 'Electrician', 'query' => 'electrician'],
    ['label' => 'Architect', 'query' => 'architect'],
    ['label' => 'Financial Adviser', 'query' => 'financial adviser'],
    ['label' => 'Mortgage Broker', 'query' => 'mortgage broker'],
    ['label' => 'Private Tutor', 'query' => 'private tutor'],
];
```

- [ ] **Step 4: Create model**

`app/Models/NicheScan.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NicheScan extends Model
{
    protected $fillable = [
        'niche',
        'niche_query',
        'city',
        'country',
        'scan_date',
        'result_count',
        'sampled_count',
        'avg_gbp_score',
        'pct_no_website',
        'pct_low_reviews',
        'opportunity_score',
        'status',
        'ran_at',
    ];

    protected function casts(): array
    {
        return [
            'scan_date' => 'date',
            'ran_at' => 'datetime',
            'avg_gbp_score' => 'float',
            'pct_no_website' => 'float',
            'pct_low_reviews' => 'float',
            'opportunity_score' => 'float',
        ];
    }
}
```

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_05_27_140000_create_niche_scans_table.php app/Models/NicheScan.php config/niches.php
git commit -m "feat(niches): add niche_scans table, model, and config list"
```

---

### Task 2: `ScanNicheJob` (TDD)

**Files:**
- Create: `tests/Feature/ScanNicheJobTest.php`
- Create: `app/Jobs/ScanNicheJob.php`

- [ ] **Step 1: Write failing job test (happy path)**

`tests/Feature/ScanNicheJobTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Jobs\ScanNicheJob;
use App\Models\NicheScan;
use App\Services\GooglePlacesService;
use App\Services\GbpScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ScanNicheJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_completes_scan_with_aggregates_and_opportunity_score(): void
    {
        config(['services.google_places.key' => 'test-key']);

        Http::fake([
            'https://places.googleapis.com/v1/places:searchText' => Http::response([
                'places' => [
                    ['id' => 'places/a'],
                    ['id' => 'places/b'],
                ],
            ], 200),
            'https://places.googleapis.com/v1/places/places/*' => Http::sequence()
                ->push([
                    'id' => 'places/a',
                    'displayName' => ['text' => 'A'],
                    'userRatingCount' => 5,
                    'photos' => [],
                ], 200)
                ->push([
                    'id' => 'places/b',
                    'displayName' => ['text' => 'B'],
                    'websiteUri' => 'https://b.example',
                    'userRatingCount' => 100,
                    'photos' => array_fill(0, 6, []),
                    'editorialSummary' => ['text' => 'Desc'],
                    'regularOpeningHours' => ['periods' => [['open' => ['day' => 1]]]],
                    'rating' => 4.5,
                    'nationalPhoneNumber' => '+441234',
                ], 200),
        ]);

        $scanDate = '2026-05-27';

        (new ScanNicheJob(
            niche: 'Dental Practice',
            nicheQuery: 'dental practice',
            city: 'Birmingham',
            country: 'GB',
            sample: 2,
            scanDate: $scanDate,
        ))->handle(
            app(GooglePlacesService::class),
            app(GbpScoringService::class),
        );

        $row = NicheScan::query()->first();

        $this->assertNotNull($row);
        $this->assertSame('complete', $row->status);
        $this->assertSame(2, $row->result_count);
        $this->assertSame(2, $row->sampled_count);
        $this->assertGreaterThan(0, $row->avg_gbp_score);
        $this->assertSame(50.0, $row->pct_no_website);
        $this->assertSame(50.0, $row->pct_low_reviews);
        $this->assertNotNull($row->opportunity_score);
        $this->assertNotNull($row->ran_at);
    }

    public function test_zero_results_completes_with_opportunity_score_zero(): void
    {
        config(['services.google_places.key' => 'test-key']);

        Http::fake([
            'https://places.googleapis.com/v1/places:searchText' => Http::response(['places' => []], 200),
        ]);

        (new ScanNicheJob(
            niche: 'Dental Practice',
            nicheQuery: 'dental practice',
            city: 'Birmingham',
            country: 'GB',
            sample: 5,
            scanDate: '2026-05-27',
        ))->handle(
            app(GooglePlacesService::class),
            app(GbpScoringService::class),
        );

        $row = NicheScan::query()->first();

        $this->assertSame('complete', $row->status);
        $this->assertSame(0, $row->result_count);
        $this->assertSame(0, $row->sampled_count);
        $this->assertSame(0.0, $row->opportunity_score);
    }
}
```

- [ ] **Step 2: Run tests — expect FAIL**

```bash
php artisan test --filter=ScanNicheJobTest
```

Expected: class `ScanNicheJob` not found.

- [ ] **Step 3: Implement `ScanNicheJob`**

`app/Jobs/ScanNicheJob.php`:

```php
<?php

namespace App\Jobs;

use App\Models\NicheScan;
use App\Services\GbpScoringService;
use App\Services\GooglePlacesService;
use App\Support\ScrapingQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ScanNicheJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 60, 120];

    public function __construct(
        public string $niche,
        public string $nicheQuery,
        public string $city,
        public string $country,
        public int $sample,
        public string $scanDate,
    ) {
        ScrapingQueue::apply($this);
    }

    public function handle(GooglePlacesService $places, GbpScoringService $scorer): void
    {
        DB::transaction(function () {
            NicheScan::query()->updateOrCreate(
                [
                    'niche' => $this->niche,
                    'city' => $this->city,
                    'scan_date' => $this->scanDate,
                ],
                [
                    'niche_query' => $this->nicheQuery,
                    'country' => $this->country,
                    'status' => 'pending',
                ],
            );
        });

        $placeIds = $places->searchByNicheAndCity($this->nicheQuery, $this->city, $this->country);
        $resultCount = count($placeIds);

        if ($resultCount === 0) {
            $this->markComplete([
                'result_count' => 0,
                'sampled_count' => 0,
                'avg_gbp_score' => 0,
                'pct_no_website' => 0,
                'pct_low_reviews' => 0,
                'opportunity_score' => 0,
            ]);

            return;
        }

        $sampleSize = min($this->sample, $resultCount);
        $sampleIds = Arr::random($placeIds, $sampleSize);
        $sampleIds = is_array($sampleIds) ? $sampleIds : [$sampleIds];

        $scores = [];
        $noWebsite = 0;
        $lowReviews = 0;
        $sampled = 0;

        foreach ($sampleIds as $placeId) {
            $payload = $places->getPlaceDetails($placeId);

            if (! $payload) {
                continue;
            }

            $sampled++;
            $scored = $scorer->score($payload, null);
            $scores[] = $scored['score'];

            if (empty($payload['websiteUri'])) {
                $noWebsite++;
            }

            if ((int) ($payload['userRatingCount'] ?? 0) < 20) {
                $lowReviews++;
            }
        }

        if ($sampled === 0) {
            $this->markComplete([
                'result_count' => $resultCount,
                'sampled_count' => 0,
                'avg_gbp_score' => 0,
                'pct_no_website' => 0,
                'pct_low_reviews' => 0,
                'opportunity_score' => 0,
            ]);

            return;
        }

        $avg = array_sum($scores) / $sampled;
        $pctNoWebsite = ($noWebsite / $sampled) * 100;
        $pctLowReviews = ($lowReviews / $sampled) * 100;

        $this->markComplete([
            'result_count' => $resultCount,
            'sampled_count' => $sampled,
            'avg_gbp_score' => round($avg, 2),
            'pct_no_website' => round($pctNoWebsite, 2),
            'pct_low_reviews' => round($pctLowReviews, 2),
            'opportunity_score' => self::opportunityScore($avg, $pctNoWebsite, $pctLowReviews),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('ScanNicheJob failed', [
            'niche' => $this->niche,
            'city' => $this->city,
            'scan_date' => $this->scanDate,
            'error' => $exception?->getMessage(),
        ]);

        NicheScan::query()
            ->where('niche', $this->niche)
            ->where('city', $this->city)
            ->whereDate('scan_date', $this->scanDate)
            ->update(['status' => 'failed']);
    }

    public static function opportunityScore(float $avgGbp, float $pctNoWebsite, float $pctLowReviews): float
    {
        return round(($avgGbp * 0.4) + ($pctNoWebsite * 0.35) + ($pctLowReviews * 0.25), 2);
    }

    /**
     * @param  array{
     *     result_count: int,
     *     sampled_count: int,
     *     avg_gbp_score: float,
     *     pct_no_website: float,
     *     pct_low_reviews: float,
     *     opportunity_score: float
     * }  $metrics
     */
    private function markComplete(array $metrics): void
    {
        DB::transaction(function () use ($metrics) {
            NicheScan::query()->updateOrCreate(
                [
                    'niche' => $this->niche,
                    'city' => $this->city,
                    'scan_date' => $this->scanDate,
                ],
                [
                    'niche_query' => $this->nicheQuery,
                    'country' => $this->country,
                    ...$metrics,
                    'status' => 'complete',
                    'ran_at' => now(),
                ],
            );
        });
    }
}
```

- [ ] **Step 4: Run tests — expect PASS**

```bash
php artisan test --filter=ScanNicheJobTest
```

- [ ] **Step 5: Commit**

```bash
git add app/Jobs/ScanNicheJob.php tests/Feature/ScanNicheJobTest.php
git commit -m "feat(niches): add ScanNicheJob with sampled GBP aggregates"
```

---

### Task 3: `niches:scan` command

**Files:**
- Create: `app/Console/Commands/ScanNichesCommand.php`
- Create: `tests/Feature/ScanNichesCommandTest.php`

- [ ] **Step 1: Write failing command test**

`tests/Feature/ScanNichesCommandTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Jobs\ScanNicheJob;
use App\Support\ScrapingQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ScanNichesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_job_per_niche_and_city(): void
    {
        Queue::fake();

        $this->travelTo(now('Europe/London')->startOfDay());

        $this->artisan('niches:scan', [
            '--cities' => 'Birmingham',
            '--niches' => 'Dental Practice,Plumber',
            '--sample' => 3,
        ])->expectsOutputToContain('Dispatched 2')
            ->assertExitCode(0);

        Queue::assertPushed(ScanNicheJob::class, 2);

        Queue::assertPushed(ScanNicheJob::class, function (ScanNicheJob $job) {
            return $job->niche === 'Dental Practice'
                && $job->city === 'Birmingham'
                && $job->sample === 3
                && $job->queue === ScrapingQueue::NAME;
        });
    }
}
```

- [ ] **Step 2: Run test — expect FAIL**

```bash
php artisan test --filter=ScanNichesCommandTest
```

- [ ] **Step 3: Implement command**

`app/Console/Commands/ScanNichesCommand.php`:

```php
<?php

namespace App\Console\Commands;

use App\Jobs\ScanNicheJob;
use App\Support\ScrapingQueue;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ScanNichesCommand extends Command
{
    protected $signature = 'niches:scan
        {--cities=Birmingham,Manchester,Leeds,Bristol,Edinburgh}
        {--niches=}
        {--sample=5}';

    protected $description = 'Dispatch niche×city GBP sample scans to the scraping queue';

    public function handle(): int
    {
        $cities = collect(explode(',', (string) $this->option('cities')))
            ->map(fn (string $c) => trim($c))
            ->filter()
            ->values();

        $nicheFilter = collect(explode(',', (string) $this->option('niches')))
            ->map(fn (string $n) => Str::lower(trim($n)))
            ->filter()
            ->values();

        $niches = collect(config('niches'))
            ->when($nicheFilter->isNotEmpty(), fn ($c) => $c->filter(
                fn (array $n) => $nicheFilter->contains(Str::lower($n['label']))
            ));

        $sample = max(1, (int) $this->option('sample'));
        $scanDate = now('Europe/London')->toDateString();
        $count = 0;

        foreach ($niches as $niche) {
            foreach ($cities as $city) {
                ScrapingQueue::dispatch(new ScanNicheJob(
                    niche: $niche['label'],
                    nicheQuery: $niche['query'],
                    city: $city,
                    country: 'GB',
                    sample: $sample,
                    scanDate: $scanDate,
                ));
                $count++;
            }
        }

        $this->info("Dispatched {$count} ScanNicheJob(s) for scan_date {$scanDate}.");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Run test — expect PASS**

```bash
php artisan test --filter=ScanNichesCommandTest
```

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/ScanNichesCommand.php tests/Feature/ScanNichesCommandTest.php
git commit -m "feat(niches): add niches:scan command to fan out jobs"
```

---

### Task 4: Scheduler

**Files:**
- Modify: `routes/console.php`

- [ ] **Step 1: Register weekly schedule**

Append to `routes/console.php` (after existing schedules):

```php
Schedule::command('niches:scan')
    ->weekly()
    ->mondays()
    ->at('06:00')
    ->timezone('Europe/London');
```

- [ ] **Step 2: Verify schedule list**

```bash
php artisan schedule:list | grep niches:scan
```

Expected: line showing `niches:scan` Monday 06:00 Europe/London.

- [ ] **Step 3: Commit**

```bash
git add routes/console.php
git commit -m "chore(niches): schedule weekly niche scan"
```

---

### Task 5: `NicheScanController`, routes, tests

**Files:**
- Create: `app/Http/Controllers/NicheScanController.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/NicheScanControllerTest.php`

- [ ] **Step 1: Write failing controller tests**

`tests/Feature/NicheScanControllerTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Jobs\ScanNicheJob;
use App\Models\NicheScan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NicheScanControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_shows_latest_scan_per_niche_city(): void
    {
        $user = User::factory()->create();

        NicheScan::query()->create([
            'niche' => 'Dental Practice',
            'niche_query' => 'dental practice',
            'city' => 'Leeds',
            'country' => 'GB',
            'scan_date' => '2026-05-20',
            'result_count' => 10,
            'sampled_count' => 5,
            'avg_gbp_score' => 50,
            'pct_no_website' => 20,
            'pct_low_reviews' => 40,
            'opportunity_score' => 45,
            'status' => 'complete',
            'ran_at' => now()->subWeek(),
        ]);

        NicheScan::query()->create([
            'niche' => 'Dental Practice',
            'niche_query' => 'dental practice',
            'city' => 'Leeds',
            'country' => 'GB',
            'scan_date' => '2026-05-27',
            'result_count' => 30,
            'sampled_count' => 5,
            'avg_gbp_score' => 70,
            'pct_no_website' => 60,
            'pct_low_reviews' => 80,
            'opportunity_score' => 90,
            'status' => 'complete',
            'ran_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/niches')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Niches/Index')
                ->has('scans', 1)
                ->where('scans.0.result_count', 30)
                ->where('scans.0.opportunity_score', 90)
            );
    }

    public function test_trigger_queues_niches_scan_command(): void
    {
        $user = User::factory()->create();

        Artisan::shouldReceive('queue')
            ->once()
            ->with('niches:scan');

        $this->actingAs($user)
            ->post('/niches/scan')
            ->assertRedirect()
            ->assertSessionHas('success', 'Scan queued');
    }
}
```

- [ ] **Step 2: Run tests — expect FAIL**

```bash
php artisan test --filter=NicheScanControllerTest
```

- [ ] **Step 3: Implement controller**

`app/Http/Controllers/NicheScanController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\NicheScan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class NicheScanController extends Controller
{
    public function index(Request $request): Response
    {
        $sort = $request->string('sort', 'opportunity_score')->toString();
        $sortColumn = $sort === 'result_count' ? 'result_count' : 'opportunity_score';

        $latestIds = NicheScan::query()
            ->select('id')
            ->whereIn('id', function ($query) {
                $query->selectRaw('(
                    SELECT ns2.id FROM niche_scans AS ns2
                    WHERE ns2.niche = niche_scans.niche
                      AND ns2.city = niche_scans.city
                    ORDER BY ns2.ran_at DESC, ns2.id DESC
                    LIMIT 1
                )');
            });

        $scans = NicheScan::query()
            ->whereIn('id', $latestIds)
            ->when($request->filled('city'), fn ($q) => $q->where('city', $request->string('city')))
            ->orderByDesc($sortColumn)
            ->get()
            ->map(fn (NicheScan $s) => [
                'niche' => $s->niche,
                'niche_query' => $s->niche_query,
                'city' => $s->city,
                'country' => $s->country,
                'result_count' => $s->result_count,
                'sampled_count' => $s->sampled_count,
                'avg_gbp_score' => $s->avg_gbp_score,
                'pct_no_website' => $s->pct_no_website,
                'pct_low_reviews' => $s->pct_low_reviews,
                'opportunity_score' => $s->opportunity_score,
                'status' => $s->status,
                'ran_at' => $s->ran_at?->toISOString(),
                'ran_at_human' => $s->ran_at?->diffForHumans() ?? '—',
            ]);

        return Inertia::render('Niches/Index', [
            'scans' => $scans,
            'cities' => NicheScan::query()->distinct()->orderBy('city')->pluck('city')->values(),
            'filters' => [
                'city' => $request->string('city')->toString() ?: null,
                'sort' => $sortColumn,
            ],
        ]);
    }

    public function trigger(): RedirectResponse
    {
        Artisan::queue('niches:scan');

        return back()->with('success', 'Scan queued');
    }
}
```

**Note:** If SQLite correlated subquery fails in tests, switch `latestIds` to:

```php
$latestIds = NicheScan::query()
    ->fromSub(
        NicheScan::query()
            ->select('*')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY niche, city ORDER BY ran_at DESC, id DESC) AS row_num'),
        'ranked'
    )
    ->where('row_num', 1)
    ->pluck('id');
```

Use whichever passes in CI (SQLite + Postgres).

- [ ] **Step 4: Add routes**

In `routes/web.php`, inside `Route::middleware('auth')->group`:

```php
use App\Http\Controllers\NicheScanController;

Route::get('/niches', [NicheScanController::class, 'index'])->name('niches.index');
Route::post('/niches/scan', [NicheScanController::class, 'trigger'])->name('niches.scan');
```

- [ ] **Step 5: Run tests — expect PASS**

```bash
php artisan test --filter=NicheScanControllerTest
```

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/NicheScanController.php routes/web.php tests/Feature/NicheScanControllerTest.php
git commit -m "feat(niches): add controller, routes, and index query"
```

---

### Task 6: Frontend `/niches` page

**Files:**
- Create: `resources/js/Pages/Niches/Index.jsx`
- Modify: `resources/js/Components/ui/AppShell.jsx`

- [ ] **Step 1: Add nav item**

In `AppShell.jsx` `navItems`, after Search:

```javascript
{ href: '/niches', label: 'Niches', match: ['niches.index'] },
```

- [ ] **Step 2: Create `Niches/Index.jsx`**

Follow `Reports/Index.jsx` patterns: `PageHeader`, `FilterBar`, `DataTable`, `Button`, `Select`, `Segmented`, `ScoreBadge`, `Status`, `Toast`, `router` from Inertia.

Key behaviours:
- Read `scans`, `cities`, `filters`, flash `success` from page props.
- **Run Now:** `router.post('/niches/scan')` + toast from flash.
- **City filter:** `router.get('/niches', { city, sort: filters.sort }, { preserveState: true })`.
- **Sort:** Segmented `opportunity_score` | `result_count` → `router.get` with `sort` param.
- Table columns per spec; `%` values formatted with one decimal (`toFixed(1)`).
- **Run Full Scan:** `router.post('/searches', { niche: row.niche_query, city: row.city, country: row.country, scan_type: 'gbp_only' })`.
- Show `Status` for `pending` / `failed` rows when not `complete`.

- [ ] **Step 3: Build frontend**

```bash
npm run build
```

Expected: build succeeds.

- [ ] **Step 4: Manual smoke (optional)**

```bash
php artisan serve
```

Visit `/niches` logged in; confirm table renders.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Niches/Index.jsx resources/js/Components/ui/AppShell.jsx
git commit -m "feat(niches): add Niches dashboard page and nav"
```

---

### Task 7: Full verification

- [ ] **Step 1: Run full test suite**

```bash
php artisan test
```

Expected: all green.

- [ ] **Step 2: Update spec status**

In `docs/superpowers/specs/2026-05-27-niche-opportunity-scanner-design.md`, set status line to:

` **Status:** Approved — plan at docs/superpowers/plans/2026-05-27-niche-opportunity-scanner.md`

- [ ] **Step 3: Final commit (docs only)**

```bash
git add docs/superpowers/specs/2026-05-27-niche-opportunity-scanner-design.md docs/superpowers/plans/2026-05-27-niche-opportunity-scanner.md
git commit -m "docs(niches): add implementation plan for niche opportunity scanner"
```

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| `niche_scans` migration + unique `(niche, city, scan_date)` | Task 1 |
| `config/niches.php` 20 entries | Task 1 |
| `niches:scan` command + options | Task 3 |
| `ScanNicheJob` on scraping queue, retries, backoff | Task 2 |
| Opportunity score formula | Task 2 (`opportunityScore`) |
| Zero results → complete, score 0 | Task 2 test |
| Weekly scheduler Europe/London | Task 4 |
| `GET /niches`, `POST /niches/scan` | Task 5 |
| Latest per niche+city index | Task 5 |
| Run Full Scan `gbp_only` + `niche_query` | Task 6 |
| UI badges via `ScoreBadge` | Task 6 |
| No prospects / no a11y | — (not implemented) |
