<?php

namespace Tests\Feature;

use App\Models\Prospect;
use App\Models\Search;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProspectTagTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_can_attach_and_detach_prospect_tag(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id]);
        $prospect = Prospect::factory()->create(['search_id' => $search->id]);

        $this->actingAs($user)->post("/prospects/{$prospect->id}/tags", [
            'action' => 'attach',
            'tag_name' => 'warm-lead',
        ])->assertRedirect();

        $tag = Tag::first();
        $this->assertNotNull($tag);
        $this->assertTrue($prospect->fresh()->tags->contains($tag));

        $this->actingAs($user)->post("/prospects/{$prospect->id}/tags", [
            'action' => 'detach',
            'tag_name' => 'warm-lead',
        ])->assertRedirect();

        $this->assertCount(0, $prospect->fresh()->tags);
    }

    public function test_smart_list_filter_by_tag(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id]);
        $tagged = Prospect::factory()->create(['search_id' => $search->id, 'combined_score' => 80]);
        $plain = Prospect::factory()->create(['search_id' => $search->id, 'combined_score' => 80]);

        $this->actingAs($user)->post("/prospects/{$tagged->id}/tags", [
            'action' => 'attach',
            'tag_name' => 'priority',
        ]);

        $list = $user->prospectLists()->create([
            'name' => 'Priority prospects',
            'type' => 'smart',
            'filter' => ['tags' => ['priority']],
        ]);

        $response = $this->actingAs($user)->get("/lists/{$list->id}");
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Lists/Show')
            ->has('rows', 1)
            ->where('rows.0.prospect.id', $tagged->id));
    }
}
