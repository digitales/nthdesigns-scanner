<?php

namespace Tests\Feature;

use App\Jobs\AuditSiteJob;
use App\Jobs\DirectUrlScanJob;
use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use App\Services\GooglePlacesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
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
            'status'  => 'pending',
        ]);

        $this->mock(GooglePlacesService::class, function ($mock) {
            $mock->shouldReceive('findByWebsiteUrl')
                ->once()
                ->with('https://example.com')
                ->andReturn([
                    'id'              => 'places/abc',
                    'displayName'     => ['text' => 'Example Ltd'],
                    'websiteUri'      => 'https://example.com',
                    'userRatingCount' => 5,
                    'photos'          => [],
                ]);
        });

        (new DirectUrlScanJob($search))->handle(
            app(GooglePlacesService::class),
            app(\App\Services\GbpScoringService::class),
            app(\App\Services\SearchStatusService::class),
            app(\App\Support\WebsiteUrlNormalizer::class),
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
            'status'  => 'pending',
        ]);

        $this->mock(GooglePlacesService::class, function ($mock) {
            $mock->shouldReceive('findByWebsiteUrl')
                ->once()
                ->andReturn(null);
        });

        (new DirectUrlScanJob($search))->handle(
            app(GooglePlacesService::class),
            app(\App\Services\GbpScoringService::class),
            app(\App\Services\SearchStatusService::class),
            app(\App\Support\WebsiteUrlNormalizer::class),
        );

        $prospect = Prospect::where('search_id', $search->id)->first();

        $this->assertNotNull($prospect);
        $this->assertStringStartsWith('direct:', $prospect->place_id);
        $this->assertSame(0, $prospect->gbp_score);
        $this->assertSame(['No GBP match found'], $prospect->gbp_flags);
        $this->assertSame('https://unknown.example', $prospect->website_url);
        Bus::assertDispatched(AuditSiteJob::class);
    }
}
