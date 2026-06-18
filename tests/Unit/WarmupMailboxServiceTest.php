<?php

namespace Tests\Unit;

use App\Models\WarmupMailbox;
use App\Services\WarmupMailboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WarmupMailboxServiceTest extends TestCase
{
    use RefreshDatabase;

    private WarmupMailboxService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(WarmupMailboxService::class);
    }

    public function test_get_daily_volume_before_warmup_starts(): void
    {
        $mailbox = WarmupMailbox::factory()->outreach()->create([
            'warmup_started_at' => null,
            'warmup_target_volume' => 50,
            'warmup_ramp_days' => 14,
        ]);

        $this->assertSame(5, $this->service->getDailyVolume($mailbox));
    }

    public function test_get_daily_volume_on_day_one(): void
    {
        Carbon::setTestNow('2026-06-17 10:00:00');

        $mailbox = WarmupMailbox::factory()->outreach()->create([
            'warmup_started_at' => now()->subDay(),
            'warmup_target_volume' => 50,
            'warmup_ramp_days' => 14,
        ]);

        $this->assertSame(8, $this->service->getDailyVolume($mailbox));
    }

    public function test_get_daily_volume_on_day_seven(): void
    {
        Carbon::setTestNow('2026-06-17 10:00:00');

        $mailbox = WarmupMailbox::factory()->outreach()->create([
            'warmup_started_at' => now()->subDays(7),
            'warmup_target_volume' => 50,
            'warmup_ramp_days' => 14,
        ]);

        $this->assertSame(28, $this->service->getDailyVolume($mailbox));
    }

    public function test_get_daily_volume_at_ramp_completion(): void
    {
        Carbon::setTestNow('2026-06-17 10:00:00');

        $mailbox = WarmupMailbox::factory()->outreach()->create([
            'warmup_started_at' => now()->subDays(14),
            'warmup_target_volume' => 50,
            'warmup_ramp_days' => 14,
        ]);

        $this->assertSame(50, $this->service->getDailyVolume($mailbox));
    }

    public function test_get_daily_volume_after_ramp(): void
    {
        Carbon::setTestNow('2026-06-17 10:00:00');

        $mailbox = WarmupMailbox::factory()->outreach()->create([
            'warmup_started_at' => now()->subDays(20),
            'warmup_target_volume' => 50,
            'warmup_ramp_days' => 14,
        ]);

        $this->assertSame(50, $this->service->getDailyVolume($mailbox));
    }
}
