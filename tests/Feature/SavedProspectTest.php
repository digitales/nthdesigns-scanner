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

    public function test_saved_page_redirects_to_lists_browse(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id]);
        Prospect::factory()->create(['search_id' => $search->id]);

        $this->actingAs($user)
            ->get('/saved')
            ->assertRedirect('/lists/browse');
    }

    public function test_saved_page_rejects_invalid_scan_type_filter(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/lists/browse?scan_type=invalid')
            ->assertSessionHasErrors('scan_type');
    }
}
