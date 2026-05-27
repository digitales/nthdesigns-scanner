# Audit Backfill Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add `scanner:backfill-audits` to find prospects with finished audit status but missing payloads, preview via dry-run, and re-queue throttled `AuditSiteJob` dispatches through the existing audit pipeline.

**Architecture:** Centralise selection in `IncompleteAuditQuery`; Artisan command handles dry-run table, reset fields, and staggered `AuditSiteJob::dispatch()->delay()`. No job changes — reset `audit_status` to `pending` satisfies existing guards.

**Tech Stack:** Laravel 13, PHPUnit, `RefreshDatabase`, `Queue::fake()`, existing `AuditSiteJob` / `AuditingQueue`.

**Spec:** `docs/superpowers/specs/2026-05-27-audit-backfill-design.md`

---

## File map

| File | Responsibility |
|------|----------------|
| `app/Support/IncompleteAuditQuery.php` | Eligible prospect query + human `reason` string |
| `app/Console/Commands/BackfillAuditsCommand.php` | CLI dry-run / execute / dispatch |
| `tests/Unit/IncompleteAuditQueryTest.php` | Selection rule coverage |
| `tests/Feature/BackfillAuditsCommandTest.php` | Dry-run + execute + queue assertions |
| `docs/deployment/laravel-cloud.md` | Operator runbook under Failed audits |

---

### Task 1: Incomplete audit query (unit)

**Files:**
- Create: `tests/Unit/IncompleteAuditQueryTest.php`
- Create: `app/Support/IncompleteAuditQuery.php`

- [ ] **Step 1: Write the failing unit test file**

Create `tests/Unit/IncompleteAuditQueryTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use App\Support\IncompleteAuditQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class IncompleteAuditQueryTest extends TestCase
{
    use RefreshDatabase;

    private function prospectForSearch(array $searchAttrs, array $prospectAttrs): Prospect
    {
        $user = User::factory()->create();
        $search = Search::factory()->create(array_merge([
            'user_id'   => $user->id,
            'scan_type' => 'combined',
            'status'    => 'complete',
        ], $searchAttrs));

        return Prospect::factory()->create(array_merge([
            'search_id'   => $search->id,
            'website_url' => 'https://example.com',
        ], $prospectAttrs));
    }

    public function test_matches_complete_prospect_with_null_lighthouse_payload(): void
    {
        $prospect = $this->prospectForSearch([], [
            'audit_status'          => 'complete',
            'raw_a11y_payload'      => ['violations' => []],
            'raw_lighthouse_payload'=> null,
        ]);

        $ids = IncompleteAuditQuery::ids();

        $this->assertContains($prospect->id, $ids);
        $this->assertSame('missing raw_lighthouse_payload', IncompleteAuditQuery::reasonFor($prospect));
    }

    public function test_does_not_match_when_lighthouse_json_present_with_zero_performance(): void
    {
        $prospect = $this->prospectForSearch([], [
            'audit_status'          => 'complete',
            'performance_score'     => 0,
            'raw_a11y_payload'      => ['violations' => []],
            'raw_lighthouse_payload'=> ['performance' => 0],
        ]);

        $this->assertNotContains($prospect->id, IncompleteAuditQuery::ids());
    }

    public function test_excludes_search_with_failed_status(): void
    {
        $prospect = $this->prospectForSearch(['status' => 'failed'], [
            'audit_status'          => 'complete',
            'raw_lighthouse_payload'=> null,
        ]);

        $this->assertNotContains($prospect->id, IncompleteAuditQuery::ids());
    }

    public function test_excludes_gbp_only_scan(): void
    {
        $prospect = $this->prospectForSearch(['scan_type' => 'gbp_only'], [
            'audit_status'          => 'complete',
            'raw_lighthouse_payload'=> null,
        ]);

        $this->assertNotContains($prospect->id, IncompleteAuditQuery::ids());
    }

    public function test_excludes_pending_audit_status(): void
    {
        $prospect = $this->prospectForSearch([], [
            'audit_status'          => 'pending',
            'raw_lighthouse_payload'=> null,
        ]);

        $this->assertNotContains($prospect->id, IncompleteAuditQuery::ids());
    }

    public function test_matches_failed_with_missing_a11y_payload(): void
    {
        $prospect = $this->prospectForSearch([], [
            'audit_status'     => 'failed',
            'raw_a11y_payload' => null,
        ]);

        $this->assertContains($prospect->id, IncompleteAuditQuery::ids());
        $this->assertSame('missing raw_a11y_payload', IncompleteAuditQuery::reasonFor($prospect));
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Unit/IncompleteAuditQueryTest.php`

Expected: FAIL — class `IncompleteAuditQuery` not found

- [ ] **Step 3: Implement `IncompleteAuditQuery`**

Create `app/Support/IncompleteAuditQuery.php`:

```php
<?php

namespace App\Support;

use App\Models\Prospect;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class IncompleteAuditQuery
{
    public static function query(): Builder
    {
        return Prospect::query()
            ->with('search')
            ->whereHas('search', fn (Builder $q) => $q->whereIn('status', ['auditing', 'complete']))
            ->whereHas('search', fn (Builder $q) => $q->whereIn('scan_type', ['accessibility_only', 'combined']))
            ->whereNotNull('website_url')
            ->where('website_url', '!=', '')
            ->whereIn('audit_status', ['complete', 'failed'])
            ->where(function (Builder $q) {
                $q->whereNull('raw_a11y_payload')
                    ->orWhereNull('raw_lighthouse_payload')
                    ->orWhere(function (Builder $q) {
                        $q->where('performance_score', 0)
                            ->whereNull('raw_lighthouse_payload');
                    });
            })
            ->orderBy('id');
    }

    /**
     * @return list<int>
     */
    public static function ids(): array
    {
        return self::query()->pluck('id')->all();
    }

    /**
     * @return Collection<int, Prospect>
     */
    public static function get(?int $searchId = null, ?int $prospectId = null, ?int $limit = null): Collection
    {
        $query = self::query();

        if ($searchId !== null) {
            $query->where('search_id', $searchId);
        }

        if ($prospectId !== null) {
            $query->whereKey($prospectId);
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    public static function reasonFor(Prospect $prospect): string
    {
        if ($prospect->raw_a11y_payload === null) {
            return 'missing raw_a11y_payload';
        }

        if ($prospect->raw_lighthouse_payload === null) {
            return 'missing raw_lighthouse_payload';
        }

        if ((int) $prospect->performance_score === 0) {
            return 'missing lighthouse performance';
        }

        return 'incomplete audit data';
    }
}
```

- [ ] **Step 4: Run unit tests**

Run: `php artisan test tests/Unit/IncompleteAuditQueryTest.php`

Expected: PASS (6 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Support/IncompleteAuditQuery.php tests/Unit/IncompleteAuditQueryTest.php
git commit -m "feat: add incomplete audit prospect query"
```

---

### Task 2: Backfill audits command (feature)

**Files:**
- Create: `tests/Feature/BackfillAuditsCommandTest.php`
- Create: `app/Console/Commands/BackfillAuditsCommand.php`

- [ ] **Step 1: Write the failing feature tests**

Create `tests/Feature/BackfillAuditsCommandTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Jobs\AuditSiteJob;
use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use App\Support\AuditingQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BackfillAuditsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function incompleteProspect(): Prospect
    {
        $user = User::factory()->create();
        $search = Search::factory()->create([
            'user_id'   => $user->id,
            'scan_type' => 'combined',
            'status'    => 'complete',
        ]);

        return Prospect::factory()->create([
            'search_id'              => $search->id,
            'website_url'            => 'https://example.com',
            'audit_status'           => 'complete',
            'a11y_score'             => 55,
            'performance_score'      => 42,
            'raw_a11y_payload'       => ['violations' => []],
            'raw_lighthouse_payload' => null,
        ]);
    }

    public function test_dry_run_does_not_modify_prospects_or_dispatch_jobs(): void
    {
        Queue::fake();

        $prospect = $this->incompleteProspect();

        $this->artisan('scanner:backfill-audits')
            ->expectsOutputToContain('Found 1')
            ->assertExitCode(0);

        $prospect->refresh();
        $this->assertSame('complete', $prospect->audit_status);
        $this->assertNotNull($prospect->raw_a11y_payload);
        Queue::assertNothingPushed();
    }

    public function test_execute_resets_prospect_and_dispatches_audit_job(): void
    {
        Queue::fake();

        $prospect = $this->incompleteProspect();

        $this->artisan('scanner:backfill-audits', ['--execute' => true, '--delay' => 0])
            ->expectsOutputToContain('dispatched 1')
            ->assertExitCode(0);

        $prospect->refresh();
        $this->assertSame('pending', $prospect->audit_status);
        $this->assertNull($prospect->raw_a11y_payload);
        $this->assertNull($prospect->raw_lighthouse_payload);
        $this->assertSame(0, $prospect->a11y_score);
        $this->assertSame(0, $prospect->performance_score);

        Queue::assertPushed(AuditSiteJob::class, function (AuditSiteJob $job) use ($prospect) {
            return $job->prospect->id === $prospect->id
                && $job->queue === AuditingQueue::NAME;
        });
    }

    public function test_no_matches_exits_successfully(): void
    {
        $this->artisan('scanner:backfill-audits')
            ->expectsOutputToContain('No incomplete audits found')
            ->assertExitCode(0);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/BackfillAuditsCommandTest.php`

Expected: FAIL — command not defined

- [ ] **Step 3: Implement `BackfillAuditsCommand`**

Create `app/Console/Commands/BackfillAuditsCommand.php`:

```php
<?php

namespace App\Console\Commands;

use App\Jobs\AuditSiteJob;
use App\Models\Prospect;
use App\Support\IncompleteAuditQuery;
use Illuminate\Console\Command;

class BackfillAuditsCommand extends Command
{
    protected $signature = 'scanner:backfill-audits
                            {--execute : Reset prospects and dispatch audit jobs}
                            {--search= : Limit to search ID}
                            {--prospect= : Limit to prospect ID}
                            {--limit= : Maximum prospects to dispatch}
                            {--delay=5 : Seconds between each dispatch}';

    protected $description = 'Find prospects with incomplete audit payloads and re-queue audits';

    public function handle(): int
    {
        $searchId = $this->option('search') !== null ? (int) $this->option('search') : null;
        $prospectId = $this->option('prospect') !== null ? (int) $this->option('prospect') : null;
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $delay = max(0, (int) $this->option('delay'));

        $total = IncompleteAuditQuery::query()
            ->when($searchId !== null, fn ($q) => $q->where('search_id', $searchId))
            ->when($prospectId !== null, fn ($q) => $q->whereKey($prospectId))
            ->count();

        $prospects = IncompleteAuditQuery::get($searchId, $prospectId, $limit);

        if ($prospects->isEmpty()) {
            $this->info('No incomplete audits found.');

            return self::SUCCESS;
        }

        $rows = $prospects->map(fn (Prospect $prospect) => [
            $prospect->id,
            $prospect->business_name,
            $prospect->search_id,
            $prospect->audit_status,
            IncompleteAuditQuery::reasonFor($prospect),
        ])->all();

        $this->table(
            ['prospect_id', 'business_name', 'search_id', 'audit_status', 'reason'],
            $rows,
        );

        $remaining = $total - $prospects->count();
        $this->info("Found {$total} incomplete audit(s).");

        if ($remaining > 0) {
            $this->warn("Showing {$prospects->count()} due to --limit; {$remaining} more match the criteria.");
        }

        if (!$this->option('execute')) {
            $this->comment('Dry run — no changes made. Pass --execute to reset and dispatch jobs.');

            return self::SUCCESS;
        }

        $dispatched = 0;

        foreach ($prospects as $index => $prospect) {
            $prospect->update([
                'audit_status'           => 'pending',
                'raw_a11y_payload'       => null,
                'raw_lighthouse_payload' => null,
                'a11y_score'             => 0,
                'a11y_flags'             => null,
                'performance_score'      => 0,
            ]);

            AuditSiteJob::dispatch($prospect->fresh())
                ->delay(now()->addSeconds($index * $delay));

            $dispatched++;
        }

        $this->info("Dispatched {$dispatched} audit job(s).");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Run feature tests**

Run: `php artisan test tests/Feature/BackfillAuditsCommandTest.php`

Expected: PASS (3 tests)

If `Queue::assertPushed` fails on connection, assert only `AuditSiteJob::class` and prospect id (drop queue name check).

- [ ] **Step 5: Run full test suite**

Run: `php artisan test`

Expected: all tests PASS

- [ ] **Step 6: Commit**

```bash
git add app/Console/Commands/BackfillAuditsCommand.php tests/Feature/BackfillAuditsCommandTest.php
git commit -m "feat: add scanner:backfill-audits command"
```

---

### Task 3: Deployment documentation

**Files:**
- Modify: `docs/deployment/laravel-cloud.md` (Failed audits subsection, ~line 567)

- [ ] **Step 1: Update Failed audits section**

Replace the sentence:

> Re-run failed prospects after fixing (new search or reset `audit_status` to `pending` and re-dispatch).

With:

```markdown
**Re-run incomplete audits after fixing the driver or browser service**

Preview prospects missing audit payloads (dry-run is the default):

```bash
php artisan scanner:backfill-audits
```

Execute (staggered dispatches to the auditing queue; adjust `--delay` if Fly is rate-limited):

```bash
php artisan scanner:backfill-audits --execute --delay=5
```

Optional filters: `--search=ID`, `--prospect=ID`, `--limit=50`. Run on an app instance; auditing workers process the jobs. See `docs/superpowers/specs/2026-05-27-audit-backfill-design.md`.
```

- [ ] **Step 2: Commit**

```bash
git add docs/deployment/laravel-cloud.md
git commit -m "docs: document scanner:backfill-audits for Cloud operators"
```

---

### Task 4: Mark spec implemented (optional)

**Files:**
- Modify: `docs/superpowers/specs/2026-05-27-audit-backfill-design.md`

- [ ] **Step 1: Set status**

Change `**Status:** Approved (pending implementation)` to `**Status:** Implemented`.

- [ ] **Step 2: Commit**

```bash
git add docs/superpowers/specs/2026-05-27-audit-backfill-design.md
git commit -m "docs: mark audit backfill spec as implemented"
```

---

## Manual verification (local)

```bash
# Create incomplete prospect via tinker or factory, then:
php artisan scanner:backfill-audits
php artisan scanner:backfill-audits --execute --delay=0 --limit=1
php artisan queue:work --queue=auditing --once
```

Confirm prospect gains `raw_lighthouse_payload` and `audit_status` becomes `complete` after pipeline runs.

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| Incomplete selection rules | Task 1 |
| Search scope auditing/complete | Task 1 tests |
| Dry-run default + table output | Task 2 |
| `--execute` reset + dispatch | Task 2 |
| `--search`, `--prospect`, `--limit`, `--delay` | Task 2 command |
| Full pipeline via existing jobs | Task 2 (no job edits) |
| Laravel Cloud docs | Task 3 |
| Unit + feature tests | Tasks 1–2 |
