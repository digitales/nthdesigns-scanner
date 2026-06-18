<?php

namespace Tests\Unit;

use App\Jobs\ProcessWarmupInboxJob;
use App\Jobs\ProcessWarmupJob;
use App\Jobs\SendWarmupEmailJob;
use App\Models\User;
use App\Models\WarmupMailbox;
use App\Services\WarmupMailboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ProcessWarmupJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_send_jobs_for_daily_volume(): void
    {
        Bus::fake([SendWarmupEmailJob::class, ProcessWarmupInboxJob::class]);

        $user = User::factory()->create();

        WarmupMailbox::factory()->outreach()->warming()->create([
            'user_id' => $user->id,
            'warmup_enabled' => true,
            'warmup_started_at' => now()->subDays(14),
            'warmup_target_volume' => 10,
            'warmup_ramp_days' => 14,
            'status' => 'warming',
            'send_window_start' => '08:00:00',
            'send_window_end' => '18:00:00',
        ]);

        WarmupMailbox::factory()->count(3)->create([
            'user_id' => $user->id,
            'is_seed_mailbox' => true,
        ]);

        (new ProcessWarmupJob)->handle(app(WarmupMailboxService::class));

        Bus::assertDispatched(SendWarmupEmailJob::class, 10);
    }

    public function test_skips_outbox_on_weekend_when_send_on_weekends_is_false(): void
    {
        Bus::fake([SendWarmupEmailJob::class, ProcessWarmupInboxJob::class]);

        Carbon::setTestNow('2026-06-20 10:00:00'); // Saturday

        $user = User::factory()->create();

        WarmupMailbox::factory()->outreach()->warming()->create([
            'user_id' => $user->id,
            'send_on_weekends' => false,
        ]);

        WarmupMailbox::factory()->create([
            'user_id' => $user->id,
            'is_seed_mailbox' => true,
        ]);

        (new ProcessWarmupJob)->handle(app(WarmupMailboxService::class));

        Bus::assertNotDispatched(SendWarmupEmailJob::class);
    }

    public function test_dispatched_delays_fall_within_send_window(): void
    {
        Bus::fake([SendWarmupEmailJob::class, ProcessWarmupInboxJob::class]);

        Carbon::setTestNow('2026-06-18 07:00:00'); // Wednesday

        $user = User::factory()->create();

        WarmupMailbox::factory()->outreach()->warming()->create([
            'user_id' => $user->id,
            'warmup_started_at' => now()->subDays(14),
            'warmup_target_volume' => 20,
            'warmup_ramp_days' => 14,
            'send_window_start' => '08:00:00',
            'send_window_end' => '18:00:00',
        ]);

        WarmupMailbox::factory()->count(5)->create([
            'user_id' => $user->id,
            'is_seed_mailbox' => true,
        ]);

        (new ProcessWarmupJob)->handle(app(WarmupMailboxService::class));

        $windowStart = Carbon::parse('2026-06-18 08:00:00');
        $windowEnd = Carbon::parse('2026-06-18 18:00:00');

        Bus::assertDispatched(SendWarmupEmailJob::class, function (SendWarmupEmailJob $job) use ($windowStart, $windowEnd) {
            $delay = $job->delay;
            $sendAt = $delay instanceof \DateTimeInterface ? Carbon::instance($delay) : now()->add($delay);

            return $sendAt->greaterThanOrEqualTo($windowStart) && $sendAt->lessThanOrEqualTo($windowEnd);
        });
    }

    public function test_seed_distribution_stays_within_ceiling_when_volume_exceeds_seed_count(): void
    {
        Bus::fake([SendWarmupEmailJob::class, ProcessWarmupInboxJob::class]);

        Carbon::setTestNow('2026-06-18 07:00:00');

        $user = User::factory()->create();

        WarmupMailbox::factory()->outreach()->warming()->create([
            'user_id' => $user->id,
            'warmup_started_at' => now()->subDays(14),
            'warmup_target_volume' => 20,
            'warmup_ramp_days' => 14,
        ]);

        $seeds = WarmupMailbox::factory()->count(3)->create([
            'user_id' => $user->id,
            'is_seed_mailbox' => true,
        ]);

        (new ProcessWarmupJob)->handle(app(WarmupMailboxService::class));

        $seedCounts = [];
        $ceiling = (int) ceil(20 / $seeds->count());

        Bus::assertDispatched(SendWarmupEmailJob::class, function (SendWarmupEmailJob $job) use (&$seedCounts) {
            $seedCounts[$job->toMailboxId] = ($seedCounts[$job->toMailboxId] ?? 0) + 1;

            return true;
        });

        foreach ($seedCounts as $count) {
            $this->assertLessThanOrEqual($ceiling, $count);
        }
    }

    public function test_skips_outbox_when_sends_already_sent_today(): void
    {
        Bus::fake([SendWarmupEmailJob::class, ProcessWarmupInboxJob::class]);

        Carbon::setTestNow('2026-06-18 10:00:00');

        $user = User::factory()->create();

        $outbox = WarmupMailbox::factory()->outreach()->warming()->create([
            'user_id' => $user->id,
        ]);

        $seed = WarmupMailbox::factory()->create([
            'user_id' => $user->id,
            'is_seed_mailbox' => true,
        ]);

        \App\Models\WarmupSend::factory()->create([
            'from_mailbox_id' => $outbox->id,
            'to_mailbox_id' => $seed->id,
            'sent_at' => now(),
        ]);

        (new ProcessWarmupJob)->handle(app(WarmupMailboxService::class));

        Bus::assertNotDispatched(SendWarmupEmailJob::class);
    }

    public function test_midday_start_schedules_sends_from_now_until_window_end(): void
    {
        Bus::fake([SendWarmupEmailJob::class, ProcessWarmupInboxJob::class]);

        Carbon::setTestNow('2026-06-18 14:00:00');

        $user = User::factory()->create();

        WarmupMailbox::factory()->outreach()->warming()->create([
            'user_id' => $user->id,
            'warmup_started_at' => now(),
            'send_window_start' => '08:00:00',
            'send_window_end' => '18:00:00',
        ]);

        WarmupMailbox::factory()->count(2)->create([
            'user_id' => $user->id,
            'is_seed_mailbox' => true,
        ]);

        (new ProcessWarmupJob)->handle(app(WarmupMailboxService::class));

        $windowStart = Carbon::parse('2026-06-18 14:00:00');
        $windowEnd = Carbon::parse('2026-06-18 18:00:00');

        Bus::assertDispatched(SendWarmupEmailJob::class, function (SendWarmupEmailJob $job) use ($windowStart, $windowEnd) {
            $delay = $job->delay;
            $sendAt = $delay instanceof \DateTimeInterface ? Carbon::instance($delay) : now()->add($delay);

            return $sendAt->greaterThanOrEqualTo($windowStart) && $sendAt->lessThanOrEqualTo($windowEnd);
        });
    }

    public function test_does_not_schedule_sends_after_send_window_has_closed(): void
    {
        Bus::fake([SendWarmupEmailJob::class, ProcessWarmupInboxJob::class]);

        Carbon::setTestNow('2026-06-18 19:00:00');

        $user = User::factory()->create();

        WarmupMailbox::factory()->outreach()->warming()->create([
            'user_id' => $user->id,
            'send_window_start' => '08:00:00',
            'send_window_end' => '18:00:00',
        ]);

        WarmupMailbox::factory()->count(2)->create([
            'user_id' => $user->id,
            'is_seed_mailbox' => true,
        ]);

        (new ProcessWarmupJob)->handle(app(WarmupMailboxService::class));

        Bus::assertNotDispatched(SendWarmupEmailJob::class);
    }
}
