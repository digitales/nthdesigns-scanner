<?php

namespace Tests\Feature;

use App\Models\OutreachEmail;
use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutreachEmailControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_mark_own_email_sent(): void
    {
        $user = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id' => Search::factory()->create(['user_id' => $user->id])->id,
        ]);
        $email = OutreachEmail::factory()->create([
            'user_id' => $user->id,
            'prospect_id' => $prospect->id,
        ]);

        $this->actingAs($user)
            ->patch("/outreach-emails/{$email->id}/sent")
            ->assertRedirect();

        $this->assertNotNull($email->fresh()->sent_at);
    }

    public function test_user_cannot_mark_another_users_email_sent(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id' => Search::factory()->create(['user_id' => $owner->id])->id,
        ]);
        $email = OutreachEmail::factory()->create([
            'user_id' => $owner->id,
            'prospect_id' => $prospect->id,
        ]);

        $this->actingAs($other)
            ->patch("/outreach-emails/{$email->id}/sent")
            ->assertForbidden();

        $this->assertNull($email->fresh()->sent_at);
    }
}
