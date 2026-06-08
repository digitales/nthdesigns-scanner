<?php

namespace Tests\Feature;

use App\Jobs\DetectCmsJob;
use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use App\Support\AuditingQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BackfillCmsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function prospectMissingCms(array $attributes = []): Prospect
    {
        $user = User::factory()->create();
        $search = Search::factory()->create([
            'user_id' => $user->id,
            'scan_type' => 'gbp_only',
            'status' => 'complete',
        ]);

        return Prospect::factory()->create(array_merge([
            'search_id' => $search->id,
            'website_url' => 'https://example.com',
            'cms_detection' => null,
        ], $attributes));
    }

    public function test_dry_run_does_not_dispatch_jobs(): void
    {
        Queue::fake();

        $this->prospectMissingCms();

        $this->artisan('scanner:backfill-cms')
            ->expectsOutputToContain('Found 1')
            ->expectsOutputToContain('Dry run')
            ->assertExitCode(0);

        Queue::assertNothingPushed();
    }

    public function test_execute_dispatches_detect_cms_job(): void
    {
        Queue::fake();

        $prospect = $this->prospectMissingCms();

        $this->artisan('scanner:backfill-cms', ['--execute' => true, '--delay' => 0])
            ->expectsOutputToContain('Dispatched 1')
            ->assertExitCode(0);

        Queue::assertPushed(DetectCmsJob::class, function (DetectCmsJob $job, ?string $queue) use ($prospect) {
            return $job->prospect->id === $prospect->id
                && $queue === AuditingQueue::NAME;
        });
    }

    public function test_skips_prospects_with_cms_detection(): void
    {
        Queue::fake();

        $this->prospectMissingCms([
            'cms_detection' => [
                'platform' => 'wordpress',
                'url' => 'https://example.com',
            ],
        ]);

        $this->artisan('scanner:backfill-cms', ['--execute' => true])
            ->expectsOutputToContain('No prospects missing CMS detection')
            ->assertExitCode(0);

        Queue::assertNothingPushed();
    }

    public function test_force_redetects_prospects_with_existing_cms_detection(): void
    {
        Queue::fake();

        $prospect = $this->prospectMissingCms([
            'cms_detection' => [
                'platform' => 'unknown',
                'url' => 'https://example.com',
            ],
        ]);

        $this->artisan('scanner:backfill-cms', ['--execute' => true, '--force' => true, '--delay' => 0])
            ->expectsOutputToContain('Found 1 prospect(s) to re-detect.')
            ->expectsOutputToContain('Dispatched 1')
            ->assertExitCode(0);

        Queue::assertPushed(DetectCmsJob::class, function (DetectCmsJob $job) use ($prospect) {
            return $job->prospect->id === $prospect->id
                && $job->force === true;
        });
    }

    public function test_skips_prospects_without_website(): void
    {
        Queue::fake();

        $this->prospectMissingCms(['website_url' => null]);

        $this->artisan('scanner:backfill-cms', ['--execute' => true])
            ->expectsOutputToContain('No prospects missing CMS detection')
            ->assertExitCode(0);

        Queue::assertNothingPushed();
    }

    public function test_limit_option(): void
    {
        Queue::fake();

        $this->prospectMissingCms(['website_url' => 'https://one.example']);
        $this->prospectMissingCms(['website_url' => 'https://two.example']);

        $this->artisan('scanner:backfill-cms', ['--execute' => true, '--limit' => 1, '--delay' => 0])
            ->expectsOutputToContain('Dispatched 1')
            ->expectsOutputToContain('1 more match')
            ->assertExitCode(0);

        Queue::assertPushed(DetectCmsJob::class, 1);
    }

    public function test_search_option(): void
    {
        Queue::fake();

        $prospect = $this->prospectMissingCms();
        $this->prospectMissingCms();

        $this->artisan('scanner:backfill-cms', [
            '--execute' => true,
            '--search' => $prospect->search_id,
            '--delay' => 0,
        ])
            ->expectsOutputToContain('Dispatched 1')
            ->assertExitCode(0);

        Queue::assertPushed(DetectCmsJob::class, 1);
    }

    public function test_execute_caps_batch_size_for_sqs_delay_limit(): void
    {
        Queue::fake();

        for ($i = 0; $i < 182; $i++) {
            $this->prospectMissingCms(['website_url' => "https://example-{$i}.test"]);
        }

        $this->artisan('scanner:backfill-cms', ['--execute' => true, '--delay' => 5])
            ->expectsOutputToContain('Dispatched 181')
            ->expectsOutputToContain('1 prospect(s) not queued')
            ->assertExitCode(0);

        Queue::assertPushed(DetectCmsJob::class, 181);
    }
}
