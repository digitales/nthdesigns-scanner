# Niche Opportunity Score Confidence Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Penalise `opportunity_score` when `result_count` is below 3 using tiered multipliers, and backfill existing `niche_scans` rows via `niches:recalculate-scores`.

**Architecture:** Extend `ScanNicheJob::opportunityScore()` with a fourth `$resultCount` argument and tiered logic. `NicheSampleCollector` passes Places result count. New Artisan command recomputes scores from stored aggregates for all complete rows. No schema or UI changes.

**Tech Stack:** Laravel 13, PHPUnit, `RefreshDatabase`, existing `NicheScan` model and `ScanNicheJob`.

**Spec:** `docs/superpowers/specs/2026-05-28-niche-opportunity-confidence-design.md`

---

## File map

| File | Responsibility |
|------|----------------|
| `app/Jobs/ScanNicheJob.php` | Tiered `opportunityScore()` static method |
| `app/Services/NicheSampleCollector.php` | Pass `$resultCount` into scoring |
| `app/Console/Commands/RecalculateNicheScoresCommand.php` | Backfill CLI with `--dry-run` |
| `tests/Unit/ScanNicheJobOpportunityScoreTest.php` | Tier table unit coverage |
| `tests/Feature/ScanNicheJobTest.php` | Single- and two-result scan integration |
| `tests/Feature/RecalculateNicheScoresCommandTest.php` | Backfill execute + dry-run |

---

### Task 1: Unit tests for tiered `opportunityScore()`

**Files:**
- Create: `tests/Unit/ScanNicheJobOpportunityScoreTest.php`
- Modify: `app/Jobs/ScanNicheJob.php`

Fixed inputs for all cases: `avgGbp = 70`, `pctNoWebsite = 100`, `pctLowReviews = 100` → raw = `(70×0.4)+(100×0.35)+(100×0.25) = 88`.

- [ ] **Step 1: Write the failing unit test file**

```php
<?php

namespace Tests\Unit;

use App\Jobs\ScanNicheJob;
use Tests\TestCase;

class ScanNicheJobOpportunityScoreTest extends TestCase
{
    private const AVG = 70.0;
    private const PCT_NO_WEBSITE = 100.0;
    private const PCT_LOW_REVIEWS = 100.0;
    private const RAW = 88.0;

    public function test_returns_zero_when_result_count_is_zero(): void
    {
        $this->assertSame(0.0, ScanNicheJob::opportunityScore(
            self::AVG,
            self::PCT_NO_WEBSITE,
            self::PCT_LOW_REVIEWS,
            0,
        ));
    }

    public function test_returns_zero_when_result_count_is_one(): void
    {
        $this->assertSame(0.0, ScanNicheJob::opportunityScore(
            self::AVG,
            self::PCT_NO_WEBSITE,
            self::PCT_LOW_REVIEWS,
            1,
        ));
    }

    public function test_returns_half_raw_when_result_count_is_two(): void
    {
        $this->assertSame(44.0, ScanNicheJob::opportunityScore(
            self::AVG,
            self::PCT_NO_WEBSITE,
            self::PCT_LOW_REVIEWS,
            2,
        ));
    }

    public function test_returns_full_raw_when_result_count_is_three_or_more(): void
    {
        $this->assertSame(self::RAW, ScanNicheJob::opportunityScore(
            self::AVG,
            self::PCT_NO_WEBSITE,
            self::PCT_LOW_REVIEWS,
            3,
        ));

        $this->assertSame(self::RAW, ScanNicheJob::opportunityScore(
            self::AVG,
            self::PCT_NO_WEBSITE,
            self::PCT_LOW_REVIEWS,
            54,
        ));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/ScanNicheJobOpportunityScoreTest.php`
Expected: FAIL — too few arguments to `opportunityScore()`

- [ ] **Step 3: Update `ScanNicheJob::opportunityScore()`**

Replace method in `app/Jobs/ScanNicheJob.php`:

```php
public static function opportunityScore(
    float $avgGbp,
    float $pctNoWebsite,
    float $pctLowReviews,
    int $resultCount,
): float {
    if ($resultCount <= 1) {
        return 0.0;
    }

    $raw = ($avgGbp * 0.4) + ($pctNoWebsite * 0.35) + ($pctLowReviews * 0.25);

    if ($resultCount === 2) {
        $raw *= 0.5;
    }

    return round($raw, 2);
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/ScanNicheJobOpportunityScoreTest.php`
Expected: PASS (4 tests)

- [ ] **Step 5: Commit**

```bash
git add tests/Unit/ScanNicheJobOpportunityScoreTest.php app/Jobs/ScanNicheJob.php
git commit -m "Apply tiered opportunity score by result count"
```

---

### Task 2: Wire `result_count` through collector

**Files:**
- Modify: `app/Services/NicheSampleCollector.php:105`

- [ ] **Step 1: Update collector call site**

Change line 105 from:

```php
'opportunity_score' => ScanNicheJob::opportunityScore($avg, $pctNoWebsite, $pctLowReviews),
```

to:

```php
'opportunity_score' => ScanNicheJob::opportunityScore($avg, $pctNoWebsite, $pctLowReviews, $resultCount),
```

- [ ] **Step 2: Run full test suite for niche scans**

Run: `php artisan test tests/Feature/ScanNicheJobTest.php tests/Unit/ScanNicheJobOpportunityScoreTest.php`
Expected: PASS (existing tests still pass; two-result job now stores halved score)

- [ ] **Step 3: Commit**

```bash
git add app/Services/NicheSampleCollector.php
git commit -m "Pass result count into niche opportunity scoring"
```

---

### Task 3: Feature tests for single- and two-result scans

**Files:**
- Modify: `tests/Feature/ScanNicheJobTest.php`

- [ ] **Step 1: Add failing test for single result**

Append to `ScanNicheJobTest.php`:

```php
public function test_single_result_completes_with_opportunity_score_zero(): void
{
    config(['services.google_places.key' => 'test-key']);

    Http::fake([
        'https://places.googleapis.com/v1/places:searchText' => Http::response([
            'places' => [['id' => 'places/a']],
        ], 200),
        'https://places.googleapis.com/v1/places/places/*' => Http::response([
            'id' => 'places/a',
            'displayName' => ['text' => 'A'],
            'userRatingCount' => 5,
            'photos' => [],
        ], 200),
    ]);

    (new ScanNicheJob(
        niche: 'Spark',
        nicheQuery: 'spark',
        city: 'Gloucester',
        country: 'GB',
        sample: 5,
        scanDate: '2026-05-28',
    ))->handle(app(NicheSampleCollector::class));

    $row = NicheScan::query()->first();

    $this->assertSame('complete', $row->status);
    $this->assertSame(1, $row->result_count);
    $this->assertSame(1, $row->sampled_count);
    $this->assertSame(0.0, $row->opportunity_score);
}
```

- [ ] **Step 2: Run test to verify it passes**

Run: `php artisan test --filter=test_single_result_completes_with_opportunity_score_zero`
Expected: PASS (logic already implemented in Task 1–2)

- [ ] **Step 3: Tighten two-result test assertion**

In `test_completes_scan_with_aggregates_and_opportunity_score`, after existing assertions add:

```php
$expected = ScanNicheJob::opportunityScore(
    $row->avg_gbp_score,
    $row->pct_no_website,
    $row->pct_low_reviews,
    $row->result_count,
);
$this->assertSame($expected, $row->opportunity_score);
```

- [ ] **Step 4: Run ScanNicheJobTest**

Run: `php artisan test tests/Feature/ScanNicheJobTest.php`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/ScanNicheJobTest.php
git commit -m "Test niche opportunity score tiers in scan job"
```

---

### Task 4: Backfill command

**Files:**
- Create: `app/Console/Commands/RecalculateNicheScoresCommand.php`
- Create: `tests/Feature/RecalculateNicheScoresCommandTest.php`

- [ ] **Step 1: Write failing feature test**

Create `tests/Feature/RecalculateNicheScoresCommandTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\NicheScan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class RecalculateNicheScoresCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_recalculates_opportunity_scores_for_complete_rows(): void
    {
        $row = NicheScan::query()->create([
            'niche' => 'Spark',
            'niche_query' => 'spark',
            'city' => 'Gloucester',
            'country' => 'GB',
            'scan_date' => '2026-05-28',
            'result_count' => 1,
            'sampled_count' => 1,
            'avg_gbp_score' => 70,
            'pct_no_website' => 100,
            'pct_low_reviews' => 100,
            'opportunity_score' => 88,
            'status' => 'complete',
            'ran_at' => now(),
        ]);

        $this->assertSame(0, Artisan::call('niches:recalculate-scores'));

        $this->assertSame(0.0, $row->fresh()->opportunity_score);
    }

    public function test_dry_run_does_not_update_rows(): void
    {
        $row = NicheScan::query()->create([
            'niche' => 'Spark',
            'niche_query' => 'spark',
            'city' => 'Gloucester',
            'country' => 'GB',
            'scan_date' => '2026-05-28',
            'result_count' => 1,
            'sampled_count' => 1,
            'avg_gbp_score' => 70,
            'pct_no_website' => 100,
            'pct_low_reviews' => 100,
            'opportunity_score' => 88,
            'status' => 'complete',
            'ran_at' => now(),
        ]);

        $this->assertSame(0, Artisan::call('niches:recalculate-scores', ['--dry-run' => true]));

        $this->assertSame(88.0, $row->fresh()->opportunity_score);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/RecalculateNicheScoresCommandTest.php`
Expected: FAIL — command not defined

- [ ] **Step 3: Create command**

Create `app/Console/Commands/RecalculateNicheScoresCommand.php`:

```php
<?php

namespace App\Console\Commands;

use App\Jobs\ScanNicheJob;
use App\Models\NicheScan;
use Illuminate\Console\Command;

class RecalculateNicheScoresCommand extends Command
{
    protected $signature = 'niches:recalculate-scores {--dry-run : Preview score changes without writing}';

    protected $description = 'Recompute opportunity_score for complete niche scans using tiered result_count confidence';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $changed = 0;
        $unchanged = 0;
        $rows = [];

        NicheScan::query()
            ->where('status', 'complete')
            ->orderBy('id')
            ->chunkById(200, function ($scans) use ($dryRun, &$changed, &$unchanged, &$rows) {
                foreach ($scans as $scan) {
                    $newScore = ScanNicheJob::opportunityScore(
                        (float) $scan->avg_gbp_score,
                        (float) $scan->pct_no_website,
                        (float) $scan->pct_low_reviews,
                        (int) $scan->result_count,
                    );

                    if ((float) $scan->opportunity_score === $newScore) {
                        $unchanged++;

                        continue;
                    }

                    $rows[] = [
                        $scan->niche,
                        $scan->city,
                        $scan->result_count,
                        $scan->opportunity_score,
                        $newScore,
                    ];

                    if (!$dryRun) {
                        $scan->update(['opportunity_score' => $newScore]);
                    }

                    $changed++;
                }
            });

        if ($rows !== []) {
            $this->table(
                ['niche', 'city', 'results', 'old_score', 'new_score'],
                $rows,
            );
        }

        $this->info("Changed: {$changed}; unchanged: {$unchanged}.");

        if ($dryRun && $changed > 0) {
            $this->comment('Dry run — no changes written. Re-run without --dry-run to apply.');
        }

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/RecalculateNicheScoresCommandTest.php`
Expected: PASS (2 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/RecalculateNicheScoresCommand.php tests/Feature/RecalculateNicheScoresCommandTest.php
git commit -m "Add niches:recalculate-scores backfill command"
```

---

### Task 5: Final verification

- [ ] **Step 1: Run targeted tests**

Run: `php artisan test tests/Unit/ScanNicheJobOpportunityScoreTest.php tests/Feature/ScanNicheJobTest.php tests/Feature/RecalculateNicheScoresCommandTest.php`
Expected: all PASS

- [ ] **Step 2: Update spec status**

In `docs/superpowers/specs/2026-05-28-niche-opportunity-confidence-design.md`, set:

```markdown
**Status:** Approved — plan at `docs/superpowers/plans/2026-05-28-niche-opportunity-confidence.md`
```

- [ ] **Step 3: Commit doc update**

```bash
git add docs/superpowers/specs/2026-05-28-niche-opportunity-confidence-design.md docs/superpowers/plans/2026-05-28-niche-opportunity-confidence.md
git commit -m "Document niche opportunity confidence rollout plan"
```

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| Tiered scoring 0 / 0.5 / 1.0 by result_count | Task 1 |
| Collector passes result_count | Task 2 |
| Single-result scan → score 0 | Task 3 |
| Two-result half score integration | Task 3 |
| `niches:recalculate-scores` | Task 4 |
| `--dry-run` | Task 4 |
| No UI/schema changes | N/A (no tasks) |

---

## Rollout (operator)

After deploy:

```bash
php artisan niches:recalculate-scores --dry-run
php artisan niches:recalculate-scores
```

Verify `/niches` default sort — former 1-result rows with scores ~80+ should drop to 0.
