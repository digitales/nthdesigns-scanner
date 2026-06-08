<?php

namespace Tests\Feature;

use App\Enums\AuditStatus;
use App\Enums\ScanType;
use App\Enums\SearchStatus;
use App\Enums\WebsiteDiscoveryConfidence;
use App\Enums\WebsiteUrlSource;
use App\Jobs\AuditSiteJob;
use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use App\Services\WebsiteDiscoveryService;
use App\Support\AuditingQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BackfillWebsitesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.brave_search.api_key' => 'test-brave',
            'scanner.website_discovery_enabled' => true,
            'scanner.website_discovery_provider' => 'brave',
        ]);
    }

    private function prospectWithoutWebsite(array $prospectAttrs = [], array $searchAttrs = []): Prospect
    {
        $user = User::factory()->create();
        $search = Search::factory()->create(array_merge([
            'user_id' => $user->id,
            'scan_type' => ScanType::Combined,
            'city' => 'Manchester',
            'country' => 'GB',
            'status' => SearchStatus::Complete,
        ], $searchAttrs));

        return Prospect::factory()->create(array_merge([
            'search_id' => $search->id,
            'business_name' => 'Briar & Wren Solicitors Ltd',
            'website_url' => null,
            'audit_status' => AuditStatus::Skipped,
            'gbp_flags' => ['No website listed'],
            'raw_gbp_payload' => [
                'id' => 'places/test',
                'displayName' => ['text' => 'Briar & Wren Solicitors Ltd'],
                'userRatingCount' => 10,
                'photos' => [],
            ],
        ], $prospectAttrs));
    }

    public function test_dry_run_lists_candidates_without_api_calls(): void
    {
        Http::fake();
        Queue::fake();

        $this->prospectWithoutWebsite();

        $this->artisan('scanner:backfill-websites')
            ->expectsOutputToContain('Found 1 prospect(s)')
            ->expectsOutputToContain('Dry run')
            ->assertExitCode(0);

        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_fails_when_discovery_not_configured(): void
    {
        config(['services.brave_search.api_key' => null]);

        $this->artisan('scanner:backfill-websites')
            ->expectsOutputToContain('not configured')
            ->assertExitCode(1);
    }

    public function test_execute_applies_match_and_queues_audit(): void
    {
        Queue::fake();

        $prospect = $this->prospectWithoutWebsite();

        Http::fake([
            'https://api.search.brave.com/res/v1/web/search*' => Http::response([
                'web' => [
                    'results' => [
                        [
                            'url' => 'https://briarwren.co.uk',
                            'title' => 'Briar & Wren Solicitors — Manchester',
                            'description' => 'Manchester solicitors',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->artisan('scanner:backfill-websites', ['--execute' => true, '--delay' => 0])
            ->expectsOutputToContain('[match]')
            ->expectsOutputToContain('Matched 1 prospect(s)')
            ->expectsOutputToContain('Queued 1 site audit(s)')
            ->assertExitCode(0);

        $prospect->refresh();
        $this->assertSame('https://briarwren.co.uk', $prospect->website_url);
        $this->assertSame(WebsiteUrlSource::Brave, $prospect->website_url_source);
        $this->assertSame(WebsiteDiscoveryConfidence::High, $prospect->website_discovery_confidence);
        $this->assertContains(WebsiteDiscoveryService::GBP_FLAG_NOT_ON_PROFILE, $prospect->gbp_flags);
        $this->assertNotContains('No website listed', $prospect->gbp_flags);
        $this->assertSame(AuditStatus::Pending, $prospect->audit_status);

        Queue::assertPushed(AuditSiteJob::class, function (AuditSiteJob $job, ?string $queue) use ($prospect) {
            return $job->prospect->id === $prospect->id
                && $queue === AuditingQueue::NAME;
        });
    }

    public function test_execute_without_audit_skips_audit_dispatch(): void
    {
        Queue::fake();

        $this->prospectWithoutWebsite();

        Http::fake([
            'https://api.search.brave.com/res/v1/web/search*' => Http::response([
                'web' => [
                    'results' => [
                        [
                            'url' => 'https://briarwren.co.uk',
                            'title' => 'Briar & Wren Solicitors — Manchester',
                            'description' => 'Manchester',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->artisan('scanner:backfill-websites', [
            '--execute' => true,
            '--no-audit' => true,
            '--delay' => 0,
        ])
            ->expectsOutputToContain('Matched 1 prospect(s)')
            ->assertExitCode(0);

        Queue::assertNothingPushed();
    }

    public function test_skips_prospects_with_website(): void
    {
        $this->prospectWithoutWebsite(['website_url' => 'https://existing.example']);

        $this->artisan('scanner:backfill-websites')
            ->expectsOutputToContain('No prospects match')
            ->assertExitCode(0);
    }

    public function test_skips_gbp_only_searches(): void
    {
        $this->prospectWithoutWebsite([], ['scan_type' => ScanType::GbpOnly]);

        $this->artisan('scanner:backfill-websites')
            ->expectsOutputToContain('No prospects match')
            ->assertExitCode(0);
    }

    public function test_search_option_limits_scope(): void
    {
        Http::fake();
        Queue::fake();

        $prospect = $this->prospectWithoutWebsite();
        $this->prospectWithoutWebsite();

        $this->artisan('scanner:backfill-websites', ['--search' => $prospect->search_id])
            ->expectsOutputToContain('Found 1 prospect(s)')
            ->assertExitCode(0);
    }
}
