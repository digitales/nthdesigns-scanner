<?php

namespace Tests\Feature;

use App\Enums\IgnoredNicheReason;
use App\Enums\NicheScanStatus;
use App\Jobs\ScanNicheJob;
use App\Models\IgnoredNiche;
use App\Models\NicheScan;
use App\Support\NicheQueue;
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

        Queue::assertPushed(ScanNicheJob::class, function (ScanNicheJob $job, ?string $queue) {
            return $job->niche === 'Dental Practice'
                && $job->city === 'Birmingham'
                && $job->sample === 3
                && $queue === NicheQueue::NAME;
        });
    }

    public function test_uses_nested_config_niches_and_default_cities(): void
    {
        Queue::fake();

        config([
            'niches' => [
                'niches' => [
                    ['label' => 'Plumber', 'query' => 'plumber', 'primary_type' => 'plumber'],
                ],
                'cities' => ['Leeds', 'Bristol'],
            ],
        ]);

        $this->artisan('niches:scan', ['--sample' => 1])
            ->expectsOutputToContain('Dispatched 2')
            ->assertExitCode(0);

        Queue::assertPushed(ScanNicheJob::class, 2);
    }

    public function test_skips_already_complete_scans_without_force(): void
    {
        Queue::fake();

        $this->travelTo(now('Europe/London')->startOfDay());

        NicheScan::query()->create([
            'niche' => 'Dental Practice',
            'niche_query' => 'dental practice',
            'city' => 'Birmingham',
            'country' => 'GB',
            'scan_date' => now('Europe/London')->toDateString(),
            'result_count' => 10,
            'sampled_count' => 5,
            'status' => NicheScanStatus::Complete,
            'ran_at' => now(),
        ]);

        $this->artisan('niches:scan', [
            '--cities' => 'Birmingham',
            '--niches' => 'Dental Practice',
        ])->expectsOutputToContain('Skipped 1')
            ->assertExitCode(0);

        Queue::assertNothingPushed();
    }

    public function test_force_passes_flag_to_dispatched_jobs(): void
    {
        Queue::fake();

        $this->travelTo(now('Europe/London')->startOfDay());

        NicheScan::query()->create([
            'niche' => 'Dental Practice',
            'niche_query' => 'dental practice',
            'city' => 'Birmingham',
            'country' => 'GB',
            'scan_date' => now('Europe/London')->toDateString(),
            'result_count' => 10,
            'sampled_count' => 5,
            'status' => NicheScanStatus::Complete,
            'ran_at' => now(),
        ]);

        $this->artisan('niches:scan', [
            '--cities' => 'Birmingham',
            '--niches' => 'Dental Practice',
            '--force' => true,
        ])->assertExitCode(0);

        Queue::assertPushed(ScanNicheJob::class, fn (ScanNicheJob $job) => $job->force === true);
    }

    public function test_excludes_ignored_niches_from_dispatch(): void
    {
        Queue::fake();

        $this->travelTo(now('Europe/London')->startOfDay());

        config([
            'niches' => [
                'niches' => [
                    ['label' => 'Plumber', 'query' => 'plumber', 'primary_type' => 'plumber'],
                    ['label' => 'Span', 'query' => 'span', 'primary_type' => 'span'],
                ],
                'cities' => ['Leeds'],
                'sample_size' => 5,
                'min_result_count' => 3,
            ],
        ]);

        IgnoredNiche::query()->create([
            'niche' => 'Span',
            'reason' => IgnoredNicheReason::Manual->value,
        ]);

        $this->artisan('niches:scan')
            ->assertExitCode(0);

        Queue::assertPushed(ScanNicheJob::class, 1);
        Queue::assertPushed(ScanNicheJob::class, fn (ScanNicheJob $job) => $job->niche === 'Plumber');
        Queue::assertNotPushed(ScanNicheJob::class, fn (ScanNicheJob $job) => $job->niche === 'Span');
    }
}
