<?php

namespace Tests\Unit;

use App\Models\WarmupMailbox;
use App\Models\WarmupSend;
use App\Services\WarmupSendService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WarmupSendServiceTest extends TestCase
{
    use RefreshDatabase;

    private WarmupSendService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(WarmupSendService::class);
    }

    public function test_calculate_deliverability_score_all_opened(): void
    {
        $mailbox = WarmupMailbox::factory()->outreach()->create();

        WarmupSend::factory()->count(4)->replied()->create([
            'from_mailbox_id' => $mailbox->id,
            'sent_at' => now()->subDays(2),
        ]);

        $this->assertSame(100, $this->service->calculateDeliverabilityScore($mailbox));
    }

    public function test_calculate_deliverability_score_none_delivered(): void
    {
        $mailbox = WarmupMailbox::factory()->outreach()->create();

        WarmupSend::factory()->count(3)->create([
            'from_mailbox_id' => $mailbox->id,
            'sent_at' => now()->subDays(2),
            'status' => 'sent',
        ]);

        $this->assertSame(0, $this->service->calculateDeliverabilityScore($mailbox));
    }

    public function test_calculate_deliverability_score_mixed_results(): void
    {
        $mailbox = WarmupMailbox::factory()->outreach()->create();

        WarmupSend::factory()->count(3)->opened()->create([
            'from_mailbox_id' => $mailbox->id,
            'sent_at' => now()->subDays(2),
        ]);

        WarmupSend::factory()->create([
            'from_mailbox_id' => $mailbox->id,
            'sent_at' => now()->subDays(2),
            'status' => 'sent',
        ]);

        $this->assertSame(75, $this->service->calculateDeliverabilityScore($mailbox));
    }

    public function test_get_estimated_ready_date_when_warming(): void
    {
        Carbon::setTestNow('2026-06-17 10:00:00');

        $mailbox = WarmupMailbox::factory()->outreach()->warming()->create([
            'warmup_ramp_days' => 14,
            'warmup_started_at' => now()->subDays(5),
            'status' => 'warming',
        ]);

        $this->assertSame(
            '2026-06-26',
            $this->service->getEstimatedReadyDate($mailbox)?->toDateString(),
        );
    }

    public function test_get_estimated_ready_date_null_when_ready(): void
    {
        $mailbox = WarmupMailbox::factory()->outreach()->create([
            'status' => 'ready',
            'warmup_started_at' => now()->subDays(20),
        ]);

        $this->assertNull($this->service->getEstimatedReadyDate($mailbox));
    }
}
