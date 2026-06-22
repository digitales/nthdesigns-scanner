<?php

namespace Tests\Unit;

use App\Jobs\ReplyToWarmupEmailJob;
use App\Jobs\RetryStaleWarmupRepliesJob;
use App\Models\WarmupMailbox;
use App\Models\WarmupSend;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class RetryStaleWarmupRepliesJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_reply_jobs_for_stale_opened_sends(): void
    {
        Bus::fake([ReplyToWarmupEmailJob::class]);

        Carbon::setTestNow('2026-06-20 12:00:00');

        $outbox = WarmupMailbox::factory()->outreach()->create();
        $seed = WarmupMailbox::factory()->create();

        $send = WarmupSend::factory()->opened()->create([
            'from_mailbox_id' => $outbox->id,
            'to_mailbox_id' => $seed->id,
            'opened_at' => now()->subHours(6),
        ]);

        (new RetryStaleWarmupRepliesJob)->handle();

        Bus::assertDispatched(ReplyToWarmupEmailJob::class, function (ReplyToWarmupEmailJob $job) use ($send, $seed) {
            return $job->sendId === $send->id && $job->fromMailboxId === $seed->id;
        });
    }

    public function test_dispatches_reply_jobs_for_stale_rescued_sends(): void
    {
        Bus::fake([ReplyToWarmupEmailJob::class]);

        Carbon::setTestNow('2026-06-20 12:00:00');

        $outbox = WarmupMailbox::factory()->outreach()->create();
        $seed = WarmupMailbox::factory()->create();

        $send = WarmupSend::factory()->rescued()->create([
            'from_mailbox_id' => $outbox->id,
            'to_mailbox_id' => $seed->id,
            'rescued_from_spam_at' => now()->subHours(6),
        ]);

        (new RetryStaleWarmupRepliesJob)->handle();

        Bus::assertDispatched(ReplyToWarmupEmailJob::class, function (ReplyToWarmupEmailJob $job) use ($send, $seed) {
            return $job->sendId === $send->id && $job->fromMailboxId === $seed->id;
        });
    }

    public function test_skips_recently_opened_sends(): void
    {
        Bus::fake([ReplyToWarmupEmailJob::class]);

        Carbon::setTestNow('2026-06-20 12:00:00');

        $outbox = WarmupMailbox::factory()->outreach()->create();
        $seed = WarmupMailbox::factory()->create();

        WarmupSend::factory()->opened()->create([
            'from_mailbox_id' => $outbox->id,
            'to_mailbox_id' => $seed->id,
            'opened_at' => now()->subHours(2),
        ]);

        (new RetryStaleWarmupRepliesJob)->handle();

        Bus::assertNotDispatched(ReplyToWarmupEmailJob::class);
    }
}
