<?php

namespace Tests\Feature;

use App\Jobs\FetchSearchCpcJob;
use App\Jobs\ScrapeProspectsJob;
use App\Models\Search;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class SearchCpcTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_search_persists_cpc_benchmark(): void
    {
        Bus::fake([ScrapeProspectsJob::class, FetchSearchCpcJob::class]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/searches', [
                'niche' => 'dental practice',
                'city' => 'Birmingham',
                'country' => 'GB',
                'scan_type' => 'combined',
                'cpc_benchmark' => 8.5,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('searches', [
            'user_id' => $user->id,
            'niche' => 'dental practice',
            'cpc_benchmark' => '8.50',
            'cpc_source' => 'manual',
        ]);
    }

    public function test_store_dispatches_fetch_cpc_job_when_auto_fetch_enabled(): void
    {
        Bus::fake([ScrapeProspectsJob::class, FetchSearchCpcJob::class]);

        Config::set('google_ads.enabled', true);
        Config::set('google_ads.auto_fetch_on_search', true);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/searches', [
                'niche' => 'dental practice',
                'city' => 'Birmingham',
                'country' => 'GB',
                'scan_type' => 'combined',
            ])
            ->assertRedirect();

        Bus::assertDispatched(FetchSearchCpcJob::class);
    }

    public function test_store_applies_existing_market_default_when_no_manual_cpc(): void
    {
        Bus::fake([ScrapeProspectsJob::class, FetchSearchCpcJob::class]);

        $user = User::factory()->create();

        app(\App\Services\MarketCpcDefaultService::class)->upsert($user, 'dental practice', 'Manchester', 'GB', [
            'cpc_benchmark' => 6.75,
            'cpc_source' => 'manual',
            'cpc_keywords' => ['dentist manchester'],
        ]);

        $this->actingAs($user)
            ->post('/searches', [
                'niche' => 'Dental Practice',
                'city' => 'Manchester',
                'country' => 'GB',
                'scan_type' => 'combined',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('searches', [
            'user_id' => $user->id,
            'cpc_benchmark' => '6.75',
            'cpc_source' => 'manual',
        ]);
    }

    public function test_update_search_cpc(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->patch("/searches/{$search->id}/cpc", [
                'cpc_benchmark' => 12.75,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $search->refresh();

        $this->assertSame('12.75', $search->cpc_benchmark);
        $this->assertSame('manual', $search->cpc_source);
        $this->assertDatabaseHas('market_cpc_defaults', [
            'user_id' => $user->id,
            'niche' => 'dental practice',
            'city' => 'birmingham',
            'cpc_benchmark' => '12.75',
        ]);
    }

    public function test_update_cpc_upserts_market_default_with_keywords(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->create([
            'user_id' => $user->id,
            'niche' => 'dental practice',
            'city' => 'Leeds',
            'country' => 'GB',
        ]);

        $this->actingAs($user)
            ->patch("/searches/{$search->id}/cpc", [
                'cpc_benchmark' => 11.5,
                'cpc_keywords' => ['dentist leeds', 'dental practice leeds'],
            ])
            ->assertRedirect();

        $search->refresh();
        $this->assertSame(['dentist leeds', 'dental practice leeds'], $search->cpc_keywords);

        $this->assertDatabaseHas('market_cpc_defaults', [
            'user_id' => $user->id,
            'niche' => 'dental practice',
            'city' => 'leeds',
            'cpc_benchmark' => '11.50',
        ]);
    }

    public function test_update_search_cpc_clears_when_null(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->create([
            'user_id' => $user->id,
            'cpc_benchmark' => 8.50,
            'cpc_source' => 'manual',
        ]);

        $this->actingAs($user)
            ->patch("/searches/{$search->id}/cpc", [
                'cpc_benchmark' => null,
            ])
            ->assertRedirect();

        $search->refresh();

        $this->assertNull($search->cpc_benchmark);
        $this->assertNull($search->cpc_source);
    }

    public function test_other_user_cannot_update_search_cpc(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($other)
            ->patch("/searches/{$search->id}/cpc", ['cpc_benchmark' => 5])
            ->assertForbidden();
    }

    public function test_import_keyword_planner_csv_saves_search_and_market_default(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->create([
            'user_id' => $user->id,
            'niche' => 'management consulting',
            'city' => 'London',
            'country' => 'GB',
        ]);

        $file = new \Illuminate\Http\UploadedFile(
            base_path('tests/fixtures/keyword-planner-export.csv'),
            'keyword-planner-export.csv',
            'text/csv',
            null,
            true,
        );

        $this->actingAs($user)
            ->post("/searches/{$search->id}/cpc/import", ['file' => $file])
            ->assertRedirect()
            ->assertSessionHas('success');

        $search->refresh();

        $this->assertSame('7.00', $search->cpc_benchmark);
        $this->assertSame('keyword_planner_csv', $search->cpc_source);
        $this->assertContains('business consultant uk', $search->cpc_keywords);

        $this->assertDatabaseHas('market_cpc_defaults', [
            'user_id' => $user->id,
            'niche' => 'management consulting',
            'city' => 'london',
            'cpc_source' => 'keyword_planner_csv',
        ]);
    }

    public function test_import_keyword_planner_csv_requires_niche_and_city(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->directUrl()->create(['user_id' => $user->id]);

        $file = new \Illuminate\Http\UploadedFile(
            base_path('tests/fixtures/keyword-planner-export.csv'),
            'keyword-planner-export.csv',
            'text/csv',
            null,
            true,
        );

        $this->actingAs($user)
            ->post("/searches/{$search->id}/cpc/import", ['file' => $file])
            ->assertRedirect()
            ->assertSessionHas('error', 'CPC import requires a niche and city search.');
    }

    public function test_other_user_cannot_import_search_cpc(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $owner->id]);

        $file = new \Illuminate\Http\UploadedFile(
            base_path('tests/fixtures/keyword-planner-export.csv'),
            'keyword-planner-export.csv',
            'text/csv',
            null,
            true,
        );

        $this->actingAs($other)
            ->post("/searches/{$search->id}/cpc/import", ['file' => $file])
            ->assertForbidden();
    }
}
