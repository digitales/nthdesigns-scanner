<?php

namespace Tests\Feature;

use App\Enums\OutreachChannel;
use App\Enums\OutreachSendSource;
use App\Models\OutreachEmail;
use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutreachEmailControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_mark_own_non_email_outreach_sent(): void
    {
        $user = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id' => Search::factory()->create(['user_id' => $user->id])->id,
        ]);
        $email = OutreachEmail::factory()->create([
            'user_id' => $user->id,
            'prospect_id' => $prospect->id,
            'channel' => OutreachChannel::ContactForm,
            'subject_line' => 'Contact form follow-up',
            'email_body' => 'Just checking in on this lead.',
        ]);

        $this->actingAs($user)
            ->patch("/outreach-emails/{$email->id}/sent")
            ->assertRedirect();

        $fresh = $email->fresh();

        $this->assertNotNull($fresh->sent_at);
        $this->assertSame('Contact form follow-up', $fresh->sent_subject);
        $this->assertSame('Just checking in on this lead.', $fresh->sent_body);
        $this->assertSame(OutreachSendSource::Manual, $fresh->send_source);
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
            'channel' => OutreachChannel::ContactForm,
        ]);

        $this->actingAs($other)
            ->patch("/outreach-emails/{$email->id}/sent")
            ->assertForbidden();

        $this->assertNull($email->fresh()->sent_at);
    }

    public function test_mark_sent_rejects_email_channel(): void
    {
        $user = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id' => Search::factory()->create(['user_id' => $user->id])->id,
        ]);
        $email = OutreachEmail::factory()->create([
            'user_id' => $user->id,
            'prospect_id' => $prospect->id,
            'channel' => OutreachChannel::Email,
        ]);

        $this->actingAs($user)
            ->patch("/outreach-emails/{$email->id}/sent")
            ->assertStatus(422);

        $this->assertNull($email->fresh()->sent_at);
    }
}
