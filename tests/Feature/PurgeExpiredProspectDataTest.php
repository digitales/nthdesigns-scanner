<?php

namespace Tests\Feature;

use App\Models\Prospect;
use App\Models\ProspectReport;
use App\Models\Search;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PurgeExpiredProspectDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_purge_clears_expired_prospect_payloads_and_reports(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id]);
        $prospect = Prospect::factory()->create([
            'search_id'       => $search->id,
            'expires_at'      => now()->subDay(),
            'raw_gbp_payload' => ['test' => true],
        ]);
        $report = ProspectReport::factory()->create([
            'prospect_id' => $prospect->id,
            'expires_at'  => now()->subDay(),
        ]);

        Artisan::call('scanner:purge-expired');

        $prospect->refresh();
        $this->assertNull($prospect->raw_gbp_payload);
        $this->assertDatabaseMissing('prospect_reports', ['id' => $report->id]);
    }
}
