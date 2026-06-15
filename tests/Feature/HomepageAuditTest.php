<?php

namespace Tests\Feature;

use App\Enums\AuditStatus;
use App\Enums\SearchSource;
use App\Enums\SearchStatus;
use App\Jobs\DirectUrlScanJob;
use App\Models\Prospect;
use App\Models\ProspectReport;
use App\Models\Search;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class HomepageAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        Config::set('scanner.homepage_audit.enabled', true);
        Config::set('scanner.homepage_audit.user_id', $this->owner->id);
        Config::set('scanner.homepage_audit.rate_limit_seconds', 60);
        Config::set('scanner.homepage_audit.hourly_limit', 5);
    }

    public function test_store_creates_homepage_search_and_dispatches_job(): void
    {
        Bus::fake([DirectUrlScanJob::class]);
        RateLimiter::clear('homepage-audit-burst:127.0.0.1');
        RateLimiter::clear('homepage-audit-hourly:127.0.0.1');

        $response = $this->postJson('/audit', [
            'website_url' => 'example.com',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['token', 'status']);

        $search = Search::first();
        $this->assertNotNull($search);
        $this->assertSame(SearchSource::Homepage, $search->source);
        $this->assertSame($this->owner->id, $search->user_id);
        $this->assertSame('https://example.com', $search->submitted_url);
        $this->assertNotNull($search->public_token);
        $this->assertSame($search->public_token, $response->json('token'));

        Bus::assertDispatched(DirectUrlScanJob::class);
    }

    public function test_store_validates_website_url(): void
    {
        $this->postJson('/audit', ['website_url' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('website_url');
    }

    public function test_store_returns_service_unavailable_when_disabled(): void
    {
        Config::set('scanner.homepage_audit.enabled', false);

        $this->postJson('/audit', ['website_url' => 'example.com'])
            ->assertServiceUnavailable();
    }

    public function test_store_returns_service_unavailable_without_owner_user(): void
    {
        Config::set('scanner.homepage_audit.user_id', null);

        $this->postJson('/audit', ['website_url' => 'example.com'])
            ->assertServiceUnavailable();
    }

    public function test_status_returns_progress_and_report_url_when_ready(): void
    {
        $search = Search::factory()->homepage('https://example.com')->create([
            'user_id' => $this->owner->id,
            'status' => SearchStatus::Complete,
        ]);

        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'website_url' => 'https://example.com',
            'audit_status' => AuditStatus::Complete,
        ]);

        $report = ProspectReport::factory()->create([
            'prospect_id' => $prospect->id,
        ]);

        $response = $this->getJson('/audit/'.$search->public_token);

        $response->assertOk()
            ->assertJson([
                'phase' => 'complete',
                'complete' => true,
                'failed' => false,
                'report_url' => url('/r/'.$report->token),
            ]);
    }

    public function test_status_returns_not_found_for_unknown_token(): void
    {
        $this->getJson('/audit/'.fake()->uuid())
            ->assertNotFound();
    }

    public function test_welcome_page_includes_homepage_audit_enabled_flag(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Welcome')
                ->where('homepageAuditEnabled', true));
    }
}
