<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WarmupMailbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarmupControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_warmup_index_requires_auth(): void
    {
        $this->get('/warmup')->assertRedirect('/login');
    }

    public function test_warmup_index_lists_user_mailboxes(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        WarmupMailbox::factory()->outreach()->create([
            'user_id' => $user->id,
            'email' => 'ross@nthdesign.co.uk',
        ]);

        WarmupMailbox::factory()->create([
            'user_id' => $other->id,
            'email' => 'other@example.com',
        ]);

        $this->actingAs($user)
            ->get('/warmup')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Warmup/Index')
                ->has('mailboxes', 1)
                ->where('mailboxes.0.email', 'ross@nthdesign.co.uk')
            );
    }

    public function test_user_cannot_view_another_users_mailbox(): void
    {
        $user = User::factory()->create();
        $mailbox = WarmupMailbox::factory()->outreach()->create([
            'user_id' => User::factory(),
        ]);

        $this->actingAs($user)
            ->get("/warmup/{$mailbox->id}")
            ->assertForbidden();
    }
}
