<?php

namespace Tests\Unit;

use App\Jobs\WarmupHealthCheckJob;
use App\Models\WarmupMailbox;
use App\Models\WarmupSend;
use App\Services\WarmupSendService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WarmupHealthCheckJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_moves_ready_mailbox_to_at_risk_when_score_drops(): void
    {
        Carbon::setTestNow('2026-06-18 10:00:00');

        $mailbox = WarmupMailbox::factory()->outreach()->create([
            'status' => 'ready',
            'warmup_started_at' => now()->subDays(20),
            'warmup_ramp_days' => 14,
            'warmup_enabled' => true,
        ]);

        WarmupSend::factory()->count(4)->create([
            'from_mailbox_id' => $mailbox->id,
            'sent_at' => now()->subDays(2),
            'status' => 'sent',
        ]);

        (new WarmupHealthCheckJob)->handle(app(WarmupSendService::class));

        $mailbox->refresh();
        $this->assertSame('at_risk', $mailbox->status);
        $this->assertSame(0, $mailbox->deliverability_score);
        $this->assertDatabaseHas('warmup_alerts', [
            'warmup_mailbox_id' => $mailbox->id,
            'type' => 'at_risk',
        ]);
    }

    public function test_moves_at_risk_mailbox_back_to_ready_on_recovery(): void
    {
        Carbon::setTestNow('2026-06-18 10:00:00');

        $mailbox = WarmupMailbox::factory()->outreach()->create([
            'status' => 'at_risk',
            'warmup_started_at' => now()->subDays(20),
            'warmup_ramp_days' => 14,
            'warmup_enabled' => true,
        ]);

        WarmupSend::factory()->count(4)->replied()->create([
            'from_mailbox_id' => $mailbox->id,
            'sent_at' => now()->subDays(2),
        ]);

        (new WarmupHealthCheckJob)->handle(app(WarmupSendService::class));

        $mailbox->refresh();
        $this->assertSame('ready', $mailbox->status);
        $this->assertSame(100, $mailbox->deliverability_score);
    }
}
