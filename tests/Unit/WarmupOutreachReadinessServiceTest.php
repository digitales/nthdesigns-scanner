<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\WarmupMailbox;
use App\Services\Warmup\WarmupOutreachReadinessService;
use App\Services\WarmupSendService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WarmupOutreachReadinessServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_no_mailbox_when_none_connected(): void
    {
        $user = User::factory()->create();

        $result = app(WarmupOutreachReadinessService::class)->forUser($user);

        $this->assertSame('no_mailbox', $result['state']);
        $this->assertNull($result['primary_email']);
    }

    public function test_reports_not_ready_with_estimated_date(): void
    {
        Carbon::setTestNow('2026-06-18 10:00:00');

        $user = User::factory()->create();
        WarmupMailbox::factory()->outreach()->create([
            'user_id' => $user->id,
            'email' => 'ross@nthdesign.co.uk',
            'status' => 'warming',
            'warmup_started_at' => now()->subDays(5),
            'warmup_ramp_days' => 14,
        ]);

        $result = app(WarmupOutreachReadinessService::class)->forUser($user);

        $this->assertSame('not_ready', $result['state']);
        $this->assertSame('ross@nthdesign.co.uk', $result['warming_email']);
        $this->assertSame(
            app(WarmupSendService::class)->getEstimatedReadyDate(
                WarmupMailbox::query()->where('user_id', $user->id)->first()
            )?->toDateString(),
            $result['estimated_ready_date'],
        );
    }

    public function test_reports_ready_when_outreach_mailbox_is_ready(): void
    {
        $user = User::factory()->create();
        WarmupMailbox::factory()->outreach()->create([
            'user_id' => $user->id,
            'email' => 'ross@nthdesign.co.uk',
            'status' => 'ready',
        ]);

        $result = app(WarmupOutreachReadinessService::class)->forUser($user);

        $this->assertSame('ready', $result['state']);
        $this->assertSame('ross@nthdesign.co.uk', $result['primary_email']);
        $this->assertCount(1, $result['ready_mailboxes']);
    }
}
