<?php

namespace Tests\Feature;

use App\Enums\ScanType;
use App\Enums\SearchSource;
use App\Jobs\DirectUrlScanJob;
use App\Models\Search;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class DirectUrlSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_direct_url_creates_search_and_dispatches_job(): void
    {
        Bus::fake([DirectUrlScanJob::class]);
        $user = User::factory()->create();
        RateLimiter::clear('search-submit:'.$user->id);

        $response = $this->actingAs($user)->post('/searches/direct', [
            'website_url' => 'example.com',
        ]);

        $search = Search::first();
        $this->assertNotNull($search);
        $this->assertSame(SearchSource::DirectUrl, $search->source);
        $this->assertSame('https://example.com', $search->submitted_url);
        $this->assertSame(ScanType::Combined, $search->scan_type);
        $this->assertNull($search->niche);
        $this->assertNull($search->city);

        $response->assertRedirect(route('searches.show', $search));
        Bus::assertDispatched(DirectUrlScanJob::class);
    }

    public function test_store_direct_url_validates_website_url(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/searches/direct', ['website_url' => ''])
            ->assertSessionHasErrors('website_url');
    }
}
