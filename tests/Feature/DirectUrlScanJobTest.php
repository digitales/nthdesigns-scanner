<?php

namespace Tests\Feature;

use App\Jobs\AuditSiteJob;
use App\Jobs\DirectUrlScanJob;
use App\Jobs\GenerateProspectReportJob;
use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use App\Services\DirectUrlSearchEnrichment;
use App\Services\GbpScoringService;
use App\Services\GooglePlacesService;
use App\Services\ProspectExclusionService;
use App\Services\SearchStatusService;
use App\Support\WebsiteUrlNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class DirectUrlScanJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_prospect_with_gbp_when_place_found(): void
    {
        Bus::fake([AuditSiteJob::class]);

        $user = User::factory()->create();
        $search = Search::factory()->directUrl('https://example.com')->create([
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        $this->mock(GooglePlacesService::class, function ($mock) {
            $mock->shouldReceive('findByWebsiteUrl')
                ->once()
                ->with('https://example.com')
                ->andReturn([
                    'id' => 'places/abc',
                    'displayName' => ['text' => 'Example Ltd'],
                    'websiteUri' => 'https://example.com',
                    'userRatingCount' => 5,
                    'photos' => [],
                ]);
        });

        (new DirectUrlScanJob($search))->handle(
            app(GooglePlacesService::class),
            app(GbpScoringService::class),
            app(SearchStatusService::class),
            app(WebsiteUrlNormalizer::class),
            app(DirectUrlSearchEnrichment::class),
            app(ProspectExclusionService::class),
        );

        $prospect = Prospect::where('search_id', $search->id)->first();

        $this->assertNotNull($prospect);
        $this->assertSame('places/abc', $prospect->place_id);
        $this->assertSame('https://example.com', $prospect->website_url);
        $this->assertGreaterThan(0, $prospect->gbp_score);
        $this->assertSame(1, $search->fresh()->total_found);
        Bus::assertDispatched(AuditSiteJob::class);
    }

    public function test_creates_prospect_without_gbp_when_not_found(): void
    {
        Bus::fake([AuditSiteJob::class]);

        $user = User::factory()->create();
        $search = Search::factory()->directUrl('https://unknown.example')->create([
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        $this->mock(GooglePlacesService::class, function ($mock) {
            $mock->shouldReceive('findByWebsiteUrl')
                ->once()
                ->andReturn(null);
        });

        (new DirectUrlScanJob($search))->handle(
            app(GooglePlacesService::class),
            app(GbpScoringService::class),
            app(SearchStatusService::class),
            app(WebsiteUrlNormalizer::class),
            app(DirectUrlSearchEnrichment::class),
            app(ProspectExclusionService::class),
        );

        $prospect = Prospect::where('search_id', $search->id)->first();

        $this->assertNotNull($prospect);
        $this->assertStringStartsWith('direct:', $prospect->place_id);
        $this->assertSame(0, $prospect->gbp_score);
        $this->assertSame(['No GBP match found'], $prospect->gbp_flags);
        $this->assertSame('https://unknown.example', $prospect->website_url);
        Bus::assertDispatched(AuditSiteJob::class);
    }

    public function test_direct_url_pipeline_completes_and_dispatches_report(): void
    {
        Bus::fake([GenerateProspectReportJob::class]);
        Config::set('scanner.audit_driver', 'skip');

        $user = User::factory()->create();
        $search = Search::factory()->directUrl('https://example.com')->create([
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        $this->mock(GooglePlacesService::class, function ($mock) {
            $mock->shouldReceive('findByWebsiteUrl')
                ->once()
                ->andReturn(null);
        });

        DirectUrlScanJob::dispatchSync($search);

        Bus::assertDispatched(GenerateProspectReportJob::class);
        $this->assertSame('complete', $search->fresh()->status);
    }

    public function test_skips_when_prospect_already_exists(): void
    {
        Bus::fake([AuditSiteJob::class]);

        $user = User::factory()->create();
        $search = Search::factory()->directUrl('https://example.com')->create([
            'user_id' => $user->id,
            'status' => 'discovering',
        ]);

        Prospect::factory()->create([
            'search_id' => $search->id,
            'place_id' => 'places/existing',
            'website_url' => 'https://example.com',
        ]);

        $this->mock(GooglePlacesService::class, function ($mock) {
            $mock->shouldNotReceive('findByWebsiteUrl');
        });

        (new DirectUrlScanJob($search))->handle(
            app(GooglePlacesService::class),
            app(GbpScoringService::class),
            app(SearchStatusService::class),
            app(WebsiteUrlNormalizer::class),
            app(DirectUrlSearchEnrichment::class),
            app(ProspectExclusionService::class),
        );

        $this->assertSame(1, Prospect::where('search_id', $search->id)->count());
        Bus::assertNotDispatched(AuditSiteJob::class);
    }

    public function test_enriches_search_from_gbp_for_report_benchmarks(): void
    {
        Bus::fake([AuditSiteJob::class]);

        $user = User::factory()->create();
        $search = Search::factory()->directUrl('https://example.com')->create([
            'user_id' => $user->id,
            'status' => 'pending',
            'country' => 'GB',
        ]);

        $this->mock(GooglePlacesService::class, function ($mock) {
            $mock->shouldReceive('findByWebsiteUrl')
                ->once()
                ->andReturn([
                    'id' => 'places/prospect',
                    'displayName' => ['text' => 'Example Dental'],
                    'websiteUri' => 'https://example.com',
                    'primaryType' => 'dentist',
                    'userRatingCount' => 5,
                    'photos' => [],
                    'addressComponents' => [
                        ['longText' => 'Wimbledon', 'types' => ['locality']],
                        ['shortText' => 'GB', 'types' => ['country']],
                    ],
                ]);

            $mock->shouldReceive('getTopRankedInNiche')
                ->once()
                ->with('dentist', 'Wimbledon', 'GB', 'places/prospect')
                ->andReturn([
                    'id' => 'places/leader',
                    'displayName' => ['text' => 'Top Dentist'],
                    'userRatingCount' => 90,
                    'photos' => array_fill(0, 15, []),
                    'rating' => 4.8,
                ]);
        });

        (new DirectUrlScanJob($search))->handle(
            app(GooglePlacesService::class),
            app(GbpScoringService::class),
            app(SearchStatusService::class),
            app(WebsiteUrlNormalizer::class),
            app(DirectUrlSearchEnrichment::class),
            app(ProspectExclusionService::class),
        );

        $search->refresh();

        $this->assertSame('dentist', $search->niche);
        $this->assertSame('Wimbledon', $search->city);
        $this->assertSame('places/leader', $search->benchmark_snapshot['place_id']);
    }
}
