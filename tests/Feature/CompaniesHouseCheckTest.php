<?php

namespace Tests\Feature;

use App\Jobs\CheckCompaniesHouseJob;
use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class CompaniesHouseCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_endpoint_queues_companies_house_job(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id' => Search::factory()->create(['user_id' => $user->id])->id,
        ]);

        $this->actingAs($user)
            ->postJson("/prospects/{$prospect->id}/companies-house/check")
            ->assertAccepted();

        Bus::assertDispatched(CheckCompaniesHouseJob::class);
    }
}
