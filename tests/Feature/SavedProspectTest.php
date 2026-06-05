<?php

namespace Tests\Feature;

use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SavedProspectTest extends TestCase
{
    use RefreshDatabase;

    public function test_saved_page_requires_auth(): void
    {
        $this->get('/saved')->assertRedirect('/login');
    }

    public function test_saved_page_lists_user_prospects(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id]);
        Prospect::factory()->create(['search_id' => $search->id]);

        $this->actingAs($user)->get('/saved')->assertOk();
    }

    public function test_saved_page_rejects_invalid_scan_type_filter(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/saved?scan_type=invalid')
            ->assertSessionHasErrors('scan_type');
    }
}
