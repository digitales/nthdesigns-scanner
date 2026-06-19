<?php

namespace Tests\Unit;

use App\Jobs\ProcessWarmupInboxJob;
use App\Jobs\RetryStaleWarmupInboxJob;
use App\Jobs\SendWarmupEmailJob;
use App\Models\WarmupMailbox;
use App\Models\WarmupSend;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class RetryStaleWarmupInboxJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_inbox_checks_for_stale_sent_records(): void
    {
        Carbon::setTestNow('2026-06-19 10:00:00');

        Bus::fake([ProcessWarmupInboxJob::class]);

        $outbox = WarmupMailbox::factory()->outreach()->create();
        $seed = WarmupMailbox::factory()->create();

        WarmupSend::factory()->create([
            'from_mailbox_id' => $outbox->id,
            'to_mailbox_id' => $seed->id,
            'status' => 'sent',
            'sent_at' => now()->subMinutes(SendWarmupEmailJob::INBOX_CHECK_DELAY_MINUTES + 90),
        ]);

        (new RetryStaleWarmupInboxJob)->handle();

        Bus::assertDispatched(ProcessWarmupInboxJob::class, function (ProcessWarmupInboxJob $job) use ($outbox, $seed) {
            return $job->outboxId === $outbox->id && $job->seedId === $seed->id;
        });
    }

    public function test_ignores_recent_sends_still_within_inbox_check_window(): void
    {
        Carbon::setTestNow('2026-06-19 10:00:00');

        Bus::fake([ProcessWarmupInboxJob::class]);

        $outbox = WarmupMailbox::factory()->outreach()->create();
        $seed = WarmupMailbox::factory()->create();

        WarmupSend::factory()->create([
            'from_mailbox_id' => $outbox->id,
            'to_mailbox_id' => $seed->id,
            'status' => 'sent',
            'sent_at' => now()->subMinutes(30),
        ]);

        (new RetryStaleWarmupInboxJob)->handle();

        Bus::assertNotDispatched(ProcessWarmupInboxJob::class);
    }
}
