<?php

namespace Tests\Unit\Outreach;

use App\Models\OutreachEmail;
use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use App\Models\WarmupMailbox;
use App\Services\Outreach\OutreachSendService;
use App\Services\ProspectUnsubscribeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OutreachSendServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_tier_blocked_when_score_below_sixty(): void
    {
        $user = User::factory()->create();

        WarmupMailbox::factory()->ready()->create([
            'user_id' => $user->id,
            'deliverability_score' => 59,
        ]);

        $readiness = app(OutreachSendService::class)->resolveTier($user);

        $this->assertSame('blocked', $readiness->tier);
        $this->assertFalse($readiness->requiresConfirmation);
    }

    public function test_resolve_tier_warn_when_ready_score_not_eighty(): void
    {
        $user = User::factory()->create();

        WarmupMailbox::factory()->ready()->create([
            'user_id' => $user->id,
            'deliverability_score' => 79,
        ]);

        $readiness = app(OutreachSendService::class)->resolveTier($user);

        $this->assertSame('warn', $readiness->tier);
        $this->assertTrue($readiness->requiresConfirmation);
    }

    public function test_resolve_tier_allowed_when_mailbox_ready(): void
    {
        $user = User::factory()->create();

        WarmupMailbox::factory()->ready()->create([
            'user_id' => $user->id,
            'deliverability_score' => 85,
        ]);

        $readiness = app(OutreachSendService::class)->resolveTier($user);

        $this->assertSame('allowed', $readiness->tier);
        $this->assertFalse($readiness->requiresConfirmation);
    }

    public function test_rejects_body_missing_unsubscribe_footer(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id]);
        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'email' => 'owner@example.com',
        ]);
        $draft = OutreachEmail::factory()->create([
            'user_id' => $user->id,
            'prospect_id' => $prospect->id,
            'subject_line' => 'Quick question',
            'email_body' => 'Plain body without footer',
        ]);

        $this->expectException(ValidationException::class);

        app(OutreachSendService::class)->validateDraft($user, $draft);
    }

    public function test_accepts_body_with_unsubscribe_footer(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id]);
        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'email' => 'owner@example.com',
        ]);

        $footerBody = app(ProspectUnsubscribeService::class)->appendUnsubscribeFooter(
            'Hello there',
            $user,
            $prospect,
            $prospect->email,
        );

        $draft = OutreachEmail::factory()->create([
            'user_id' => $user->id,
            'prospect_id' => $prospect->id,
            'subject_line' => 'Quick question',
            'email_body' => $footerBody,
        ]);

        app(OutreachSendService::class)->validateDraft($user, $draft);

        $this->assertTrue(true);
    }
}
