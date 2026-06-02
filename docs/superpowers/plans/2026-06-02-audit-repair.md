# Audit Repair Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add `scanner:repair-audits` to kickstart stuck site audits, retry all failed site audits, and retry failed/stuck screenshot captures — without re-running Google Places / GBP scoring.

**Architecture:** Three query support classes (`StuckSiteAuditQuery`, `FailedSiteAuditQuery`, `FailedScreenshotQuery`) plus connection-aware `AuditingQueuePresence`. Command orchestrates dry-run tables and execute paths. Site-audit repair uses new `ProspectAuditService::repairSiteAudit()` (like `queueSiteAudit` but allows already-`pending` stuck prospects). Screenshot repair dispatches `CaptureScreenshotJob` directly.

**Tech Stack:** Laravel 13, PHPUnit, `RefreshDatabase`, `Queue::fake()`, existing `AuditSiteJob` / `CaptureScreenshotJob` / `AuditingQueue`.

**Spec:** `docs/superpowers/specs/2026-06-02-audit-repair-design.md`

---

## File map

| File | Responsibility |
|------|----------------|
| `app/Support/RepairAuditScope.php` | Shared search/website scope for site-audit queries |
| `app/Support/AuditingQueuePresence.php` | Connection-aware pending job detection |
| `app/Support/StuckSiteAuditQuery.php` | Stuck pending site audit selection |
| `app/Support/FailedSiteAuditQuery.php` | Failed site audit selection (deduped) |
| `app/Support/FailedScreenshotQuery.php` | Failed/stuck screenshot selection |
| `app/Support/StaleAuditJobCloser.php` | Close stale `running` audit_jobs before re-dispatch |
| `app/Services/ProspectAuditService.php` | Add `repairSiteAudit()` |
| `app/Console/Commands/RepairAuditsCommand.php` | CLI dry-run / execute |
| `tests/Unit/AuditingQueuePresenceTest.php` | Queue presence |
| `tests/Unit/StuckSiteAuditQueryTest.php` | Stuck selection |
| `tests/Unit/FailedSiteAuditQueryTest.php` | Failed selection |
| `tests/Unit/FailedScreenshotQueryTest.php` | Screenshot selection |
| `tests/Feature/RepairAuditsCommandTest.php` | Command integration |
| `docs/deployment/laravel-cloud.md` | Operator runbook |

---

### Task 1: Shared repair scope

**Files:**
- Create: `app/Support/RepairAuditScope.php`

- [ ] **Step 1: Create `RepairAuditScope`**

```php
<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

final class RepairAuditScope
{
    public static function applySiteAuditProspectScope(Builder $query): Builder
    {
        return $query
            ->with('search')
            ->whereHas('search', fn (Builder $q) => $q->whereIn('status', ['auditing', 'complete']))
            ->whereHas('search', fn (Builder $q) => $q->whereIn('scan_type', ['accessibility_only', 'combined']))
            ->whereNotNull('website_url')
            ->where('website_url', '!=', '');
    }

    public static function siteAuditsDisabledByDriver(): bool
    {
        return config('scanner.audit_driver') === 'skip';
    }

    public static function applySearchProspectFilters(
        Builder $query,
        ?int $searchId,
        ?int $prospectId,
    ): Builder {
        if ($searchId !== null) {
            $query->where('search_id', $searchId);
        }

        if ($prospectId !== null) {
            $query->whereKey($prospectId);
        }

        return $query;
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Support/RepairAuditScope.php
git commit -m "feat: add shared repair audit query scope"
```

---

### Task 2: Auditing queue presence

**Files:**
- Create: `tests/Unit/AuditingQueuePresenceTest.php`
- Create: `app/Support/AuditingQueuePresence.php`

- [ ] **Step 1: Write failing unit tests**

Create `tests/Unit/AuditingQueuePresenceTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Jobs\AuditSiteJob;
use App\Jobs\CaptureScreenshotJob;
use App\Models\Prospect;
use App\Models\ProspectReport;
use App\Models\Search;
use App\Models\User;
use App\Support\AuditingQueue;
use App\Support\AuditingQueuePresence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditingQueuePresenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_connection_detects_pending_audit_site_job(): void
    {
        Config::set('scanner.auditing_queue_connection', 'database');

        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id, 'scan_type' => 'combined']);
        $prospect = Prospect::factory()->create([
            'search_id'   => $search->id,
            'website_url' => 'https://example.com',
        ]);

        $this->assertFalse(AuditingQueuePresence::hasPendingAuditSiteJob($prospect->id));

        AuditSiteJob::dispatch($prospect);

        $this->assertTrue(AuditingQueuePresence::hasPendingAuditSiteJob($prospect->id));
    }

    public function test_database_connection_detects_pending_screenshot_job(): void
    {
        Config::set('scanner.auditing_queue_connection', 'database');

        $report = ProspectReport::factory()->create();

        $this->assertFalse(AuditingQueuePresence::hasPendingScreenshotJob($report->id));

        CaptureScreenshotJob::dispatch($report);

        $this->assertTrue(AuditingQueuePresence::hasPendingScreenshotJob($report->id));
    }

    public function test_cloud_connection_skips_queue_check(): void
    {
        Config::set('scanner.auditing_queue_connection', 'cloud');

        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id]);
        $prospect = Prospect::factory()->create(['search_id' => $search->id]);

        AuditSiteJob::dispatch($prospect);

        $this->assertFalse(AuditingQueuePresence::hasPendingAuditSiteJob($prospect->id));
        $this->assertTrue(AuditingQueuePresence::skipsQueueCheck());
    }

    public function test_ignores_completed_jobs_in_database(): void
    {
        Config::set('scanner.auditing_queue_connection', 'database');

        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id]);
        $prospect = Prospect::factory()->create(['search_id' => $search->id]);

        DB::table('jobs')->insert([
            'queue'        => AuditingQueue::NAME,
            'payload'      => json_encode(['displayName' => AuditSiteJob::class]),
            'attempts'     => 0,
            'reserved_at'  => now()->timestamp,
            'available_at' => now()->timestamp,
            'created_at'   => now()->timestamp,
        ]);

        $this->assertFalse(AuditingQueuePresence::hasPendingAuditSiteJob($prospect->id));
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Unit/AuditingQueuePresenceTest.php`

Expected: FAIL — class not found

- [ ] **Step 3: Implement `AuditingQueuePresence`**

Create `app/Support/AuditingQueuePresence.php`:

```php
<?php

namespace App\Support;

use App\Jobs\AuditSiteJob;
use App\Jobs\CaptureScreenshotJob;
use Illuminate\Support\Facades\DB;

final class AuditingQueuePresence
{
    public static function skipsQueueCheck(): bool
    {
        return AuditingQueue::connection() === 'cloud';
    }

    public static function hasPendingAuditSiteJob(int $prospectId): bool
    {
        if (self::skipsQueueCheck()) {
            return false;
        }

        return self::hasPendingJob(AuditSiteJob::class, $prospectId);
    }

    public static function hasPendingScreenshotJob(int $reportId): bool
    {
        if (self::skipsQueueCheck()) {
            return false;
        }

        return self::hasPendingJob(CaptureScreenshotJob::class, $reportId);
    }

    private static function hasPendingJob(string $jobClass, int $modelId): bool
    {
        $shortName = class_basename($jobClass);

        return DB::connection(AuditingQueue::connection())
            ->table('jobs')
            ->where('queue', AuditingQueue::NAME)
            ->whereNull('reserved_at')
            ->where('payload', 'like', '%'.$shortName.'%')
            ->where('payload', 'like', '%"id";i:'.$modelId.';%')
            ->exists();
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Unit/AuditingQueuePresenceTest.php`

Expected: PASS (4 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Support/AuditingQueuePresence.php tests/Unit/AuditingQueuePresenceTest.php
git commit -m "feat: add connection-aware auditing queue presence helper"
```

---

### Task 3: Stuck site audit query

**Files:**
- Create: `tests/Unit/StuckSiteAuditQueryTest.php`
- Create: `app/Support/StuckSiteAuditQuery.php`

- [ ] **Step 1: Write failing unit tests**

Create `tests/Unit/StuckSiteAuditQueryTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Jobs\AuditSiteJob;
use App\Models\AuditJob;
use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use App\Support\StuckSiteAuditQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class StuckSiteAuditQueryTest extends TestCase
{
    use RefreshDatabase;

    private function stalePendingProspect(array $searchAttrs = [], array $prospectAttrs = []): Prospect
    {
        $user = User::factory()->create();
        $search = Search::factory()->create(array_merge([
            'user_id'   => $user->id,
            'scan_type' => 'combined',
            'status'    => 'auditing',
        ], $searchAttrs));

        $prospect = Prospect::factory()->create(array_merge([
            'search_id'    => $search->id,
            'website_url'  => 'https://example.com',
            'audit_status' => 'pending',
        ], $prospectAttrs));

        $prospect->forceFill(['updated_at' => now()->subMinutes(20)])->save();

        return $prospect->fresh();
    }

    public function test_matches_stale_pending_without_queue_job(): void
    {
        Config::set('scanner.auditing_queue_connection', 'database');

        $prospect = $this->stalePendingProspect();

        $ids = StuckSiteAuditQuery::ids(stuckAfterMinutes: 15);

        $this->assertContains($prospect->id, $ids);
        $this->assertStringContainsString('pending without queue job', StuckSiteAuditQuery::reasonFor($prospect, 15));
    }

    public function test_does_not_match_fresh_pending(): void
    {
        Config::set('scanner.auditing_queue_connection', 'database');

        $prospect = $this->stalePendingProspect();
        $prospect->forceFill(['updated_at' => now()->subMinutes(5)])->save();

        $this->assertNotContains($prospect->id, StuckSiteAuditQuery::ids(15));
    }

    public function test_does_not_match_when_queue_job_present(): void
    {
        Config::set('scanner.auditing_queue_connection', 'database');

        $prospect = $this->stalePendingProspect();

        AuditSiteJob::dispatch($prospect);

        $this->assertNotContains($prospect->id, StuckSiteAuditQuery::ids(15));
    }

    public function test_matches_stale_running_accessibility_audit_job(): void
    {
        Config::set('scanner.auditing_queue_connection', 'database');

        $prospect = $this->stalePendingProspect();

        AuditJob::create([
            'prospect_id' => $prospect->id,
            'job_type'    => 'accessibility',
            'status'      => 'running',
            'started_at'  => now()->subMinutes(20),
        ]);

        $this->assertContains($prospect->id, StuckSiteAuditQuery::ids(15));
        $this->assertStringContainsString('running audit_job', StuckSiteAuditQuery::reasonFor($prospect, 15));
    }

    public function test_excludes_when_audit_driver_skip(): void
    {
        Config::set('scanner.audit_driver', 'skip');
        Config::set('scanner.auditing_queue_connection', 'database');

        $prospect = $this->stalePendingProspect();

        $this->assertNotContains($prospect->id, StuckSiteAuditQuery::ids(15));
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Unit/StuckSiteAuditQueryTest.php`

Expected: FAIL — class not found

- [ ] **Step 3: Implement `StuckSiteAuditQuery`**

Create `app/Support/StuckSiteAuditQuery.php`:

```php
<?php

namespace App\Support;

use App\Models\AuditJob;
use App\Models\Prospect;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class StuckSiteAuditQuery
{
    public static function query(int $stuckAfterMinutes): Builder
    {
        if (RepairAuditScope::siteAuditsDisabledByDriver()) {
            return Prospect::query()->whereRaw('0 = 1');
        }

        $cutoff = now()->subMinutes($stuckAfterMinutes);

        return RepairAuditScope::applySiteAuditProspectScope(Prospect::query())
            ->where('audit_status', 'pending')
            ->where(function (Builder $q) use ($cutoff) {
                $q->where('updated_at', '<', $cutoff)
                    ->orWhereHas('auditJobs', function (Builder $job) use ($cutoff) {
                        $job->where('job_type', 'accessibility')
                            ->where('status', 'running')
                            ->where('started_at', '<', $cutoff);
                    });
            })
            ->orderBy('id');
    }

    public static function ids(int $stuckAfterMinutes): array
    {
        return self::filterByQueuePresence(self::query($stuckAfterMinutes)->get(), $stuckAfterMinutes)
            ->pluck('id')
            ->all();
    }

    public static function get(
        ?int $searchId,
        ?int $prospectId,
        ?int $limit,
        int $stuckAfterMinutes,
    ): Collection {
        $query = RepairAuditScope::applySearchProspectFilters(
            self::query($stuckAfterMinutes),
            $searchId,
            $prospectId,
        );

        if ($limit !== null) {
            $query->limit($limit);
        }

        return self::filterByQueuePresence($query->get(), $stuckAfterMinutes);
    }

    public static function reasonFor(Prospect $prospect, int $stuckAfterMinutes): string
    {
        $runningJob = AuditJob::query()
            ->where('prospect_id', $prospect->id)
            ->where('job_type', 'accessibility')
            ->where('status', 'running')
            ->latest('id')
            ->first();

        if ($runningJob && $runningJob->started_at?->lt(now()->subMinutes($stuckAfterMinutes))) {
            return "running audit_job #{$runningJob->id} stale without queue job";
        }

        $ageMinutes = $prospect->updated_at
            ? (int) $prospect->updated_at->diffInMinutes(now())
            : $stuckAfterMinutes;

        return "pending without queue job (stale {$ageMinutes}m)";
    }

    private static function filterByQueuePresence(Collection $prospects, int $stuckAfterMinutes): Collection
    {
        return $prospects->filter(function (Prospect $prospect) use ($stuckAfterMinutes) {
            if (AuditingQueuePresence::hasPendingAuditSiteJob($prospect->id)) {
                return false;
            }

            $cutoff = now()->subMinutes($stuckAfterMinutes);

            if ($prospect->updated_at?->gte($cutoff)) {
                $hasStaleRunning = AuditJob::query()
                    ->where('prospect_id', $prospect->id)
                    ->where('job_type', 'accessibility')
                    ->where('status', 'running')
                    ->where('started_at', '<', $cutoff)
                    ->exists();

                if (! $hasStaleRunning) {
                    return false;
                }
            }

            return true;
        })->values();
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Unit/StuckSiteAuditQueryTest.php`

Expected: PASS (5 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Support/StuckSiteAuditQuery.php tests/Unit/StuckSiteAuditQueryTest.php
git commit -m "feat: add stuck site audit repair query"
```

---

### Task 4: Failed site audit query

**Files:**
- Create: `tests/Unit/FailedSiteAuditQueryTest.php`
- Create: `app/Support/FailedSiteAuditQuery.php`

- [ ] **Step 1: Write failing unit tests**

Create `tests/Unit/FailedSiteAuditQueryTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\AuditJob;
use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use App\Support\FailedSiteAuditQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class FailedSiteAuditQueryTest extends TestCase
{
    use RefreshDatabase;

    private function failedProspect(array $prospectAttrs = []): Prospect
    {
        $user = User::factory()->create();
        $search = Search::factory()->create([
            'user_id'   => $user->id,
            'scan_type' => 'combined',
            'status'    => 'complete',
        ]);

        return Prospect::factory()->create(array_merge([
            'search_id'              => $search->id,
            'website_url'            => 'https://example.com',
            'audit_status'           => 'failed',
            'raw_a11y_payload'       => ['violations' => []],
            'raw_lighthouse_payload' => ['performance' => 80],
            'performance_score'      => 80,
        ], $prospectAttrs));
    }

    public function test_matches_failed_even_with_complete_payloads(): void
    {
        $prospect = $this->failedProspect();

        $this->assertContains($prospect->id, FailedSiteAuditQuery::ids());
        $this->assertStringStartsWith('audit_status failed', FailedSiteAuditQuery::reasonFor($prospect));
    }

    public function test_appends_latest_error_message(): void
    {
        $prospect = $this->failedProspect();

        AuditJob::create([
            'prospect_id'   => $prospect->id,
            'job_type'      => 'accessibility',
            'status'        => 'failed',
            'error_message' => 'Playwright timeout',
            'completed_at'  => now(),
        ]);

        $this->assertStringContainsString('Playwright timeout', FailedSiteAuditQuery::reasonFor($prospect));
    }

    public function test_excludes_stuck_prospect_ids(): void
    {
        $prospect = $this->failedProspect();

        $this->assertNotContains($prospect->id, FailedSiteAuditQuery::ids(excludeProspectIds: [$prospect->id]));
    }

    public function test_excludes_when_audit_driver_skip(): void
    {
        Config::set('scanner.audit_driver', 'skip');

        $prospect = $this->failedProspect();

        $this->assertNotContains($prospect->id, FailedSiteAuditQuery::ids());
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Unit/FailedSiteAuditQueryTest.php`

Expected: FAIL — class not found

- [ ] **Step 3: Implement `FailedSiteAuditQuery`**

Create `app/Support/FailedSiteAuditQuery.php`:

```php
<?php

namespace App\Support;

use App\Models\AuditJob;
use App\Models\Prospect;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class FailedSiteAuditQuery
{
    public static function query(array $excludeProspectIds = []): Builder
    {
        if (RepairAuditScope::siteAuditsDisabledByDriver()) {
            return Prospect::query()->whereRaw('0 = 1');
        }

        $query = RepairAuditScope::applySiteAuditProspectScope(Prospect::query())
            ->where('audit_status', 'failed')
            ->orderBy('id');

        if ($excludeProspectIds !== []) {
            $query->whereNotIn('id', $excludeProspectIds);
        }

        return $query;
    }

    public static function ids(array $excludeProspectIds = []): array
    {
        return self::query($excludeProspectIds)->pluck('id')->all();
    }

    public static function get(
        ?int $searchId,
        ?int $prospectId,
        ?int $limit,
        array $excludeProspectIds = [],
    ): Collection {
        $query = RepairAuditScope::applySearchProspectFilters(
            self::query($excludeProspectIds),
            $searchId,
            $prospectId,
        );

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    public static function reasonFor(Prospect $prospect): string
    {
        $reason = 'audit_status failed';

        $latest = AuditJob::query()
            ->where('prospect_id', $prospect->id)
            ->where('job_type', 'accessibility')
            ->where('status', 'failed')
            ->latest('id')
            ->value('error_message');

        if ($latest) {
            $reason .= ': '.$latest;
        }

        return $reason;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Unit/FailedSiteAuditQueryTest.php`

Expected: PASS (4 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Support/FailedSiteAuditQuery.php tests/Unit/FailedSiteAuditQueryTest.php
git commit -m "feat: add failed site audit repair query"
```

---

### Task 5: Failed screenshot query

**Files:**
- Create: `tests/Unit/FailedScreenshotQueryTest.php`
- Create: `app/Support/FailedScreenshotQuery.php`

- [ ] **Step 1: Write failing unit tests**

Create `tests/Unit/FailedScreenshotQueryTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Jobs\CaptureScreenshotJob;
use App\Models\AuditJob;
use App\Models\Prospect;
use App\Models\ProspectReport;
use App\Models\Search;
use App\Models\User;
use App\Support\FailedScreenshotQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class FailedScreenshotQueryTest extends TestCase
{
    use RefreshDatabase;

    private function reportWithProspect(array $prospectAttrs = []): ProspectReport
    {
        $user = User::factory()->create();
        $search = Search::factory()->create([
            'user_id' => $user->id,
            'status'  => 'complete',
        ]);
        $prospect = Prospect::factory()->create(array_merge([
            'search_id'    => $search->id,
            'website_url'  => 'https://example.com',
            'audit_status' => 'complete',
        ], $prospectAttrs));

        return ProspectReport::factory()->create(['prospect_id' => $prospect->id]);
    }

    public function test_matches_failed_screenshot_job(): void
    {
        $report = $this->reportWithProspect();

        AuditJob::create([
            'prospect_id'  => $report->prospect_id,
            'job_type'     => 'screenshot',
            'status'       => 'failed',
            'completed_at' => now(),
        ]);

        $ids = FailedScreenshotQuery::ids(stuckAfterMinutes: 15);

        $this->assertContains($report->id, $ids);
        $this->assertSame('screenshot failed', FailedScreenshotQuery::reasonFor($report));
    }

    public function test_matches_stale_running_screenshot_without_queue_job(): void
    {
        Config::set('scanner.auditing_queue_connection', 'database');

        $report = $this->reportWithProspect();

        AuditJob::create([
            'prospect_id' => $report->prospect_id,
            'job_type'    => 'screenshot',
            'status'      => 'running',
            'started_at'  => now()->subMinutes(20),
        ]);

        $this->assertContains($report->id, FailedScreenshotQuery::ids(15));
    }

    public function test_does_not_match_when_screenshot_queue_job_present(): void
    {
        Config::set('scanner.auditing_queue_connection', 'database');

        $report = $this->reportWithProspect();

        AuditJob::create([
            'prospect_id' => $report->prospect_id,
            'job_type'    => 'screenshot',
            'status'      => 'running',
            'started_at'  => now()->subMinutes(20),
        ]);

        CaptureScreenshotJob::dispatch($report);

        $this->assertNotContains($report->id, FailedScreenshotQuery::ids(15));
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Unit/FailedScreenshotQueryTest.php`

Expected: FAIL — class not found

- [ ] **Step 3: Implement `FailedScreenshotQuery`**

Create `app/Support/FailedScreenshotQuery.php`:

```php
<?php

namespace App\Support;

use App\Models\AuditJob;
use App\Models\ProspectReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class FailedScreenshotQuery
{
    public static function query(int $stuckAfterMinutes): Builder
    {
        return ProspectReport::query()
            ->with(['prospect.search'])
            ->whereHas('prospect', fn (Builder $q) => $q
                ->whereNotNull('website_url')
                ->where('website_url', '!=', ''))
            ->whereHas('prospect.search', fn (Builder $q) => $q
                ->whereIn('status', ['auditing', 'complete']))
            ->orderBy('id');
    }

    public static function ids(int $stuckAfterMinutes): array
    {
        return self::filterEligible(self::query($stuckAfterMinutes)->get(), $stuckAfterMinutes)
            ->pluck('id')
            ->all();
    }

    public static function get(
        ?int $searchId,
        ?int $prospectId,
        ?int $limit,
        int $stuckAfterMinutes,
    ): Collection {
        $query = self::query($stuckAfterMinutes);

        if ($searchId !== null) {
            $query->whereHas('prospect', fn (Builder $q) => $q->where('search_id', $searchId));
        }

        if ($prospectId !== null) {
            $query->where('prospect_id', $prospectId);
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        return self::filterEligible($query->get(), $stuckAfterMinutes);
    }

    public static function reasonFor(ProspectReport $report): string
    {
        $latest = self::latestScreenshotJob($report->prospect_id);

        if ($latest?->status === 'running') {
            return 'screenshot running stale without queue job';
        }

        return 'screenshot failed';
    }

    private static function filterEligible(Collection $reports, int $stuckAfterMinutes): Collection
    {
        $cutoff = now()->subMinutes($stuckAfterMinutes);

        return $reports->filter(function (ProspectReport $report) use ($cutoff, $stuckAfterMinutes) {
            $latest = self::latestScreenshotJob($report->prospect_id);

            if (! $latest || ! in_array($latest->status, ['failed', 'running'], true)) {
                return false;
            }

            if ($latest->status === 'failed') {
                return true;
            }

            if ($latest->started_at?->gte($cutoff)) {
                return false;
            }

            return ! AuditingQueuePresence::hasPendingScreenshotJob($report->id);
        })->values();
    }

    private static function latestScreenshotJob(int $prospectId): ?AuditJob
    {
        return AuditJob::query()
            ->where('prospect_id', $prospectId)
            ->where('job_type', 'screenshot')
            ->latest('id')
            ->first();
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Unit/FailedScreenshotQueryTest.php`

Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Support/FailedScreenshotQuery.php tests/Unit/FailedScreenshotQueryTest.php
git commit -m "feat: add failed screenshot repair query"
```

---

### Task 6: Stale audit job closer + repairSiteAudit service method

**Files:**
- Create: `app/Support/StaleAuditJobCloser.php`
- Modify: `app/Services/ProspectAuditService.php`
- Create: `tests/Unit/ProspectAuditServiceRepairTest.php`

- [ ] **Step 1: Write failing service test**

Create `tests/Unit/ProspectAuditServiceRepairTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Jobs\AuditSiteJob;
use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use App\Services\ProspectAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProspectAuditServiceRepairTest extends TestCase
{
    use RefreshDatabase;

    public function test_repair_site_audit_allows_already_pending_prospect(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $search = Search::factory()->create([
            'user_id'   => $user->id,
            'scan_type' => 'combined',
        ]);
        $prospect = Prospect::factory()->create([
            'search_id'        => $search->id,
            'website_url'      => 'https://example.com',
            'audit_status'     => 'pending',
            'raw_a11y_payload' => ['partial' => true],
        ]);

        app(ProspectAuditService::class)->repairSiteAudit($prospect);

        $prospect->refresh();
        $this->assertSame('pending', $prospect->audit_status);
        $this->assertNull($prospect->raw_a11y_payload);

        Queue::assertPushed(AuditSiteJob::class, fn (AuditSiteJob $job) => $job->prospect->id === $prospect->id);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/ProspectAuditServiceRepairTest.php`

Expected: FAIL — method not defined

- [ ] **Step 3: Implement closer and service method**

Create `app/Support/StaleAuditJobCloser.php`:

```php
<?php

namespace App\Support;

use App\Models\AuditJob;

final class StaleAuditJobCloser
{
    public const MESSAGE = 'Closed by scanner:repair-audits (stale)';

    public static function closeRunning(int $prospectId, string $jobType): int
    {
        return AuditJob::query()
            ->where('prospect_id', $prospectId)
            ->where('job_type', $jobType)
            ->where('status', 'running')
            ->update([
                'status'        => 'failed',
                'error_message' => self::MESSAGE,
                'completed_at'  => now(),
            ]);
    }
}
```

Add to `app/Services/ProspectAuditService.php`:

```php
/**
 * Reset site-audit fields and queue {@see AuditSiteJob} for repair flows.
 * Unlike {@see queueSiteAudit()}, allows prospects already pending (stuck re-dispatch).
 */
public function repairSiteAudit(Prospect $prospect, bool $suppressAutoReport = true, int $delaySeconds = 0): void
{
    $prospect->loadMissing('search');

    if (empty($prospect->website_url)) {
        throw ValidationException::withMessages([
            'website_url' => 'Add a website URL before running a site audit.',
        ]);
    }

    if (! in_array($prospect->search->scan_type, ['accessibility_only', 'combined'], true)) {
        throw ValidationException::withMessages([
            'website_url' => 'This search type does not include site audits.',
        ]);
    }

    $prospect->update(array_merge($this->auditResetFields(), [
        'suppress_auto_report' => $suppressAutoReport,
    ]));

    $dispatch = AuditSiteJob::dispatch($prospect->fresh());

    if ($delaySeconds > 0) {
        $dispatch->delay(now()->addSeconds($delaySeconds));
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/ProspectAuditServiceRepairTest.php`

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Support/StaleAuditJobCloser.php app/Services/ProspectAuditService.php tests/Unit/ProspectAuditServiceRepairTest.php
git commit -m "feat: add repair site audit service path for stuck prospects"
```

---

### Task 7: Repair audits command

**Files:**
- Create: `tests/Feature/RepairAuditsCommandTest.php`
- Create: `app/Console/Commands/RepairAuditsCommand.php`

- [ ] **Step 1: Write failing feature tests**

Create `tests/Feature/RepairAuditsCommandTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Jobs\AuditSiteJob;
use App\Jobs\CaptureScreenshotJob;
use App\Models\AuditJob;
use App\Models\Prospect;
use App\Models\ProspectReport;
use App\Models\Search;
use App\Models\User;
use App\Support\AuditingQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RepairAuditsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function searchProspect(string $auditStatus, array $extra = []): Prospect
    {
        $user = User::factory()->create();
        $search = Search::factory()->create([
            'user_id'   => $user->id,
            'scan_type' => 'combined',
            'status'    => 'auditing',
        ]);

        return Prospect::factory()->create(array_merge([
            'search_id'    => $search->id,
            'website_url'  => 'https://example.com',
            'audit_status' => $auditStatus,
        ], $extra));
    }

    public function test_dry_run_lists_categories_without_dispatching(): void
    {
        Queue::fake();
        Config::set('scanner.auditing_queue_connection', 'database');

        $stuck = $this->searchProspect('pending');
        $stuck->forceFill(['updated_at' => now()->subMinutes(20)])->save();

        $failed = $this->searchProspect('failed');

        $report = ProspectReport::factory()->create(['prospect_id' => $failed->id]);
        AuditJob::create([
            'prospect_id'  => $failed->id,
            'job_type'     => 'screenshot',
            'status'       => 'failed',
            'completed_at' => now(),
        ]);

        $this->artisan('scanner:repair-audits', ['--stuck-after' => 15])
            ->expectsOutputToContain('stuck:')
            ->expectsOutputToContain('failed:')
            ->expectsOutputToContain('screenshots:')
            ->assertExitCode(0);

        Queue::assertNothingPushed();
    }

    public function test_execute_stuck_closes_running_job_and_dispatches_audit(): void
    {
        Queue::fake();
        Config::set('scanner.auditing_queue_connection', 'database');

        $prospect = $this->searchProspect('pending');
        $prospect->forceFill(['updated_at' => now()->subMinutes(20)])->save();

        $running = AuditJob::create([
            'prospect_id' => $prospect->id,
            'job_type'    => 'accessibility',
            'status'      => 'running',
            'started_at'  => now()->subMinutes(20),
        ]);

        $this->artisan('scanner:repair-audits', [
            '--execute'    => true,
            '--only'       => 'stuck',
            '--stuck-after'=> 15,
            '--delay'      => 0,
        ])->assertExitCode(0);

        $running->refresh();
        $this->assertSame('failed', $running->status);
        $this->assertSame('Closed by scanner:repair-audits (stale)', $running->error_message);

        Queue::assertPushed(AuditSiteJob::class, fn (AuditSiteJob $job) => $job->prospect->id === $prospect->id);
    }

    public function test_execute_failed_resets_and_dispatches_audit(): void
    {
        Queue::fake();

        $prospect = $this->searchProspect('failed', [
            'raw_a11y_payload'       => ['violations' => []],
            'raw_lighthouse_payload' => ['performance' => 90],
            'performance_score'      => 90,
        ]);

        $this->artisan('scanner:repair-audits', [
            '--execute' => true,
            '--only'    => 'failed',
            '--delay'   => 0,
        ])->assertExitCode(0);

        $prospect->refresh();
        $this->assertSame('pending', $prospect->audit_status);
        $this->assertNull($prospect->raw_a11y_payload);

        Queue::assertPushed(AuditSiteJob::class);
    }

    public function test_execute_screenshot_dispatches_capture_job_only(): void
    {
        Queue::fake();

        $prospect = $this->searchProspect('complete');
        $report = ProspectReport::factory()->create(['prospect_id' => $prospect->id]);

        AuditJob::create([
            'prospect_id'  => $prospect->id,
            'job_type'     => 'screenshot',
            'status'       => 'failed',
            'completed_at' => now(),
        ]);

        $this->artisan('scanner:repair-audits', [
            '--execute' => true,
            '--only'    => 'screenshots',
            '--delay'   => 0,
        ])->assertExitCode(0);

        $prospect->refresh();
        $this->assertSame('complete', $prospect->audit_status);

        Queue::assertPushed(CaptureScreenshotJob::class, function (CaptureScreenshotJob $job) use ($report) {
            return $job->report->id === $report->id
                && $job->queue === AuditingQueue::NAME;
        });
        Queue::assertNotPushed(AuditSiteJob::class);
    }

    public function test_no_matches_exits_successfully(): void
    {
        $this->artisan('scanner:repair-audits')
            ->expectsOutputToContain('Nothing to repair')
            ->assertExitCode(0);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/RepairAuditsCommandTest.php`

Expected: FAIL — command not defined

- [ ] **Step 3: Implement `RepairAuditsCommand`**

Create `app/Console/Commands/RepairAuditsCommand.php`:

```php
<?php

namespace App\Console\Commands;

use App\Jobs\CaptureScreenshotJob;
use App\Models\Prospect;
use App\Models\ProspectReport;
use App\Services\ProspectAuditService;
use App\Support\AuditingQueuePresence;
use App\Support\FailedScreenshotQuery;
use App\Support\FailedSiteAuditQuery;
use App\Support\QueueDispatchDelay;
use App\Support\StaleAuditJobCloser;
use App\Support\StuckSiteAuditQuery;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RepairAuditsCommand extends Command
{
    protected $signature = 'scanner:repair-audits
                            {--execute : Close stale jobs and dispatch repairs}
                            {--search= : Limit to search ID}
                            {--prospect= : Limit to prospect ID}
                            {--limit= : Maximum items to dispatch per category}
                            {--delay=5 : Seconds between each dispatch}
                            {--stuck-after=15 : Minutes before pending/running counts as stale}
                            {--only= : Restrict to stuck, failed, or screenshots}
                            {--skip-screenshots : Skip screenshot repairs}';

    protected $description = 'Repair stuck site audits, retry failed site audits, and retry failed/stuck screenshots';

    public function handle(ProspectAuditService $audits): int
    {
        $searchId = $this->option('search') !== null ? (int) $this->option('search') : null;
        $prospectId = $this->option('prospect') !== null ? (int) $this->option('prospect') : null;
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $delay = max(0, (int) $this->option('delay'));
        $stuckAfter = max(1, (int) $this->option('stuck-after'));
        $only = $this->option('only');

        $includeStuck = $only === null || $only === 'stuck';
        $includeFailed = $only === null || $only === 'failed';
        $includeScreenshots = ($only === null || $only === 'screenshots') && ! $this->option('skip-screenshots');

        $stuck = $includeStuck
            ? StuckSiteAuditQuery::get($searchId, $prospectId, $limit, $stuckAfter)
            : collect();

        $stuckIds = $stuck->pluck('id')->all();

        $failed = $includeFailed
            ? FailedSiteAuditQuery::get($searchId, $prospectId, $limit, $stuckIds)
            : collect();

        $screenshots = $includeScreenshots
            ? FailedScreenshotQuery::get($searchId, $prospectId, $limit, $stuckAfter)
            : collect();

        if ($stuck->isEmpty() && $failed->isEmpty() && $screenshots->isEmpty()) {
            $this->info('Nothing to repair.');

            return self::SUCCESS;
        }

        $this->info("stuck: {$stuck->count()}, failed: {$failed->count()}, screenshots: {$screenshots->count()}");

        if (AuditingQueuePresence::skipsQueueCheck() && $stuck->isNotEmpty()) {
            $this->comment('Queue check skipped (cloud connection) — stuck detection uses age threshold only.');
        }

        $rows = $this->buildTableRows($stuck, $failed, $screenshots, $stuckAfter);

        $this->table(
            ['category', 'prospect_id', 'report_id', 'search_id', 'reason'],
            $rows,
        );

        $maxPerBatch = QueueDispatchDelay::maxJobsPerBatch($delay);
        $totalDispatches = $stuck->count() + $failed->count() + $screenshots->count();

        if ($maxPerBatch !== null && $totalDispatches > $maxPerBatch) {
            $this->warn("With --delay={$delay}, each run can queue at most {$maxPerBatch} job(s) on SQS (".QueueDispatchDelay::MAX_SECONDS.'s cap). Re-run until none remain.');
        }

        if (! $this->option('execute')) {
            $this->comment('Dry run — no changes made. Pass --execute to repair and dispatch jobs.');

            return self::SUCCESS;
        }

        $dispatchIndex = 0;
        $siteDispatched = 0;
        $screenshotDispatched = 0;

        foreach ($stuck as $prospect) {
            if ($maxPerBatch !== null && $dispatchIndex >= $maxPerBatch) {
                break;
            }

            StaleAuditJobCloser::closeRunning($prospect->id, 'accessibility');
            $audits->repairSiteAudit(
                $prospect->fresh(),
                suppressAutoReport: true,
                delaySeconds: QueueDispatchDelay::forIndex($dispatchIndex, $delay),
            );
            $dispatchIndex++;
            $siteDispatched++;
        }

        foreach ($failed as $prospect) {
            if ($maxPerBatch !== null && $dispatchIndex >= $maxPerBatch) {
                break;
            }

            StaleAuditJobCloser::closeRunning($prospect->id, 'accessibility');
            $audits->repairSiteAudit(
                $prospect->fresh(),
                suppressAutoReport: true,
                delaySeconds: QueueDispatchDelay::forIndex($dispatchIndex, $delay),
            );
            $dispatchIndex++;
            $siteDispatched++;
        }

        foreach ($screenshots as $report) {
            if ($maxPerBatch !== null && $dispatchIndex >= $maxPerBatch) {
                break;
            }

            StaleAuditJobCloser::closeRunning($report->prospect_id, 'screenshot');

            $pending = CaptureScreenshotJob::dispatch($report);

            $delaySeconds = QueueDispatchDelay::forIndex($dispatchIndex, $delay);
            if ($delaySeconds > 0) {
                $pending->delay(now()->addSeconds($delaySeconds));
            }

            $dispatchIndex++;
            $screenshotDispatched++;
        }

        $this->info("Dispatched {$siteDispatched} site audit(s), {$screenshotDispatched} screenshot(s).");

        return self::SUCCESS;
    }

    /**
     * @return list<array{0: string, 1: int, 2: string, 3: int, 4: string}>
     */
    private function buildTableRows(
        Collection $stuck,
        Collection $failed,
        Collection $screenshots,
        int $stuckAfter,
    ): array {
        $rows = [];

        foreach ($stuck as $prospect) {
            /** @var Prospect $prospect */
            $rows[] = [
                'stuck',
                $prospect->id,
                '—',
                $prospect->search_id,
                StuckSiteAuditQuery::reasonFor($prospect, $stuckAfter),
            ];
        }

        foreach ($failed as $prospect) {
            /** @var Prospect $prospect */
            $rows[] = [
                'failed',
                $prospect->id,
                '—',
                $prospect->search_id,
                FailedSiteAuditQuery::reasonFor($prospect),
            ];
        }

        foreach ($screenshots as $report) {
            /** @var ProspectReport $report */
            $rows[] = [
                'screenshots',
                $report->prospect_id,
                (string) $report->id,
                $report->prospect->search_id,
                FailedScreenshotQuery::reasonFor($report),
            ];
        }

        return $rows;
    }
}
```

- [ ] **Step 4: Run feature tests**

Run: `php artisan test tests/Feature/RepairAuditsCommandTest.php`

Expected: PASS (5 tests)

- [ ] **Step 5: Run related unit tests**

Run: `php artisan test tests/Unit/AuditingQueuePresenceTest.php tests/Unit/StuckSiteAuditQueryTest.php tests/Unit/FailedSiteAuditQueryTest.php tests/Unit/FailedScreenshotQueryTest.php tests/Unit/ProspectAuditServiceRepairTest.php`

Expected: all PASS

- [ ] **Step 6: Commit**

```bash
git add app/Console/Commands/RepairAuditsCommand.php tests/Feature/RepairAuditsCommandTest.php
git commit -m "feat: add scanner:repair-audits command"
```

---

### Task 8: Operator documentation

**Files:**
- Modify: `docs/deployment/laravel-cloud.md` (near **Failed audits**, ~line 759)

- [ ] **Step 1: Add Audit repair subsection**

After the existing **Failed audits** backfill block, add:

```markdown
**Repair stuck, failed, or screenshot audits**

When searches stay in **Auditing**, prospects remain `audit_status: pending` without a worker, or screenshot captures failed:

```bash
php artisan scanner:repair-audits              # dry-run (all categories)
php artisan scanner:repair-audits --execute --delay=5
php artisan scanner:repair-audits --execute --only=stuck --stuck-after=10
```

This command does **not** re-run Google Places / GBP scoring. It re-dispatches `AuditSiteJob` for stuck or failed site audits and `CaptureScreenshotJob` for failed/stuck screenshots. For incomplete audit *payloads* on finished prospects, use `scanner:backfill-audits` instead.

See `docs/superpowers/specs/2026-06-02-audit-repair-design.md`.
```

- [ ] **Step 2: Commit**

```bash
git add docs/deployment/laravel-cloud.md
git commit -m "docs: document scanner:repair-audits for Cloud operators"
```

---

### Task 9: Final verification

- [ ] **Step 1: Run full test suite**

Run: `php artisan test`

Expected: all tests PASS

- [ ] **Step 2: Smoke-test CLI**

Run: `php artisan scanner:repair-audits`

Expected: `Nothing to repair.` on empty DB (or summary table in dev)

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| Stuck site audit query + age + queue check | Task 3 |
| Failed site audit query (all failed) | Task 4 |
| Failed/stuck screenshot query | Task 5 |
| Cloud connection age-only | Task 2 (`skipsQueueCheck`) |
| Close stale running audit_jobs | Task 6 |
| repairSiteAudit for pending stuck | Task 6 |
| No GBP re-scrape | Tasks 6–7 (AuditSiteJob only) |
| Dry-run default + `--execute` | Task 7 |
| Category flags `--only`, `--skip-screenshots` | Task 7 |
| Throttling / SQS cap warning | Task 7 |
| Operator docs | Task 8 |
