<?php

namespace Tests\Feature;

use App\Models\Prospect;
use App\Models\ProspectReport;
use App\Models\Search;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_page_requires_auth(): void
    {
        $this->get('/reports')->assertRedirect('/login');
    }

    public function test_reports_page_loads_for_user(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id]);
        $prospect = Prospect::factory()->create(['search_id' => $search->id]);
        ProspectReport::factory()->create(['prospect_id' => $prospect->id]);

        $this->actingAs($user)->get('/reports')->assertOk();
    }

    public function test_reports_page_rejects_invalid_viewed_filter(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/reports?viewed=maybe')
            ->assertSessionHasErrors('viewed');
    }
}
