<?php

namespace Tests\Feature;

use App\Enums\OutreachChannel;
use App\Enums\OutreachSendSource;
use App\Models\OutreachEmail;
use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use App\Models\WarmupMailbox;
use App\Services\MailboxTransportFactory;
use App\Services\ProspectUnsubscribeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Mailer\Transport\NullTransport;
use Tests\TestCase;

class OutreachSendTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_persists_sent_metadata_when_allowed(): void
    {
        $user = User::factory()->create();
        $prospect = $this->makeProspect($user, 'owner@example.com');
        $mailbox = WarmupMailbox::factory()->ready()->create([
            'user_id' => $user->id,
            'deliverability_score' => 90,
        ]);
        $draft = $this->makeEmailDraft($user, $prospect);

        $this->mock(MailboxTransportFactory::class, function ($mock) {
            $mock->shouldReceive('make')->andReturn(new NullTransport);
        });

        $this->actingAs($user)
            ->post(route('outreach.send', $draft))
            ->assertRedirect()
            ->assertSessionHas('success');

        $fresh = $draft->fresh();

        $this->assertNotNull($fresh->sent_at);
        $this->assertSame($mailbox->id, $fresh->from_mailbox_id);
        $this->assertNotNull($fresh->smtp_message_id);
        $this->assertSame($fresh->subject_line, $fresh->sent_subject);
        $this->assertSame($fresh->email_body, $fresh->sent_body);
        $this->assertSame(OutreachSendSource::App, $fresh->send_source);
    }

    public function test_blocked_tier_returns_422(): void
    {
        $user = User::factory()->create();
        $prospect = $this->makeProspect($user, 'owner@example.com');
        WarmupMailbox::factory()->ready()->create([
            'user_id' => $user->id,
            'deliverability_score' => 59,
        ]);
        $draft = $this->makeEmailDraft($user, $prospect);

        $this->actingAs($user)
            ->post(route('outreach.send', $draft))
            ->assertStatus(422);

        $this->assertNull($draft->fresh()->sent_at);
    }

    public function test_warn_tier_requires_confirm_warned(): void
    {
        $user = User::factory()->create();
        $prospect = $this->makeProspect($user, 'owner@example.com');
        WarmupMailbox::factory()->ready()->create([
            'user_id' => $user->id,
            'deliverability_score' => 79,
        ]);
        $draft = $this->makeEmailDraft($user, $prospect);

        $this->actingAs($user)
            ->post(route('outreach.send', $draft))
            ->assertStatus(422);

        $this->assertNull($draft->fresh()->sent_at);
    }

    public function test_draft_patch_updates_working_copy_not_generated(): void
    {
        $user = User::factory()->create();
        $prospect = $this->makeProspect($user, 'owner@example.com');
        $draft = OutreachEmail::factory()->create([
            'user_id' => $user->id,
            'prospect_id' => $prospect->id,
            'channel' => OutreachChannel::Email,
            'subject_line' => 'Initial subject',
            'email_body' => $this->footerBody($user, $prospect, 'Initial body'),
            'generated_subject' => 'Original generated subject',
            'generated_body' => 'Original generated body',
            'sent_at' => null,
        ]);

        $updatedBody = $this->footerBody($user, $prospect, 'Updated body');

        $this->actingAs($user)
            ->patch(route('outreach.update', $draft), [
                'subject_line' => 'Updated subject',
                'email_body' => $updatedBody,
            ])
            ->assertRedirect();

        $fresh = $draft->fresh();

        $this->assertSame('Updated subject', $fresh->subject_line);
        $this->assertSame($updatedBody, $fresh->email_body);
        $this->assertSame('Original generated subject', $fresh->generated_subject);
        $this->assertSame('Original generated body', $fresh->generated_body);
    }

    private function makeProspect(User $user, string $email): Prospect
    {
        $search = Search::factory()->create(['user_id' => $user->id]);

        return Prospect::factory()->create([
            'search_id' => $search->id,
            'email' => $email,
        ]);
    }

    private function makeEmailDraft(User $user, Prospect $prospect): OutreachEmail
    {
        return OutreachEmail::factory()->create([
            'user_id' => $user->id,
            'prospect_id' => $prospect->id,
            'channel' => OutreachChannel::Email,
            'subject_line' => 'Quick question',
            'email_body' => $this->footerBody($user, $prospect, 'Hello there'),
            'sent_at' => null,
        ]);
    }

    private function footerBody(User $user, Prospect $prospect, string $body): string
    {
        return app(ProspectUnsubscribeService::class)->appendUnsubscribeFooter(
            $body,
            $user,
            $prospect,
            (string) $prospect->email,
        );
    }
}
