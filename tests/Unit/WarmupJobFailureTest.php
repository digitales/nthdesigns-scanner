<?php

namespace Tests\Unit;

use App\Jobs\ProcessWarmupInboxJob;
use App\Jobs\ReplyToWarmupEmailJob;
use App\Jobs\SendWarmupEmailJob;
use App\Models\WarmupAlert;
use App\Models\WarmupMailbox;
use App\Services\WarmupSendService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class WarmupJobFailureTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_job_marks_mailbox_failed_after_three_consecutive_failures(): void
    {
        $mailbox = WarmupMailbox::factory()->outreach()->warming()->create([
            'consecutive_failures' => 2,
        ]);

        $job = new SendWarmupEmailJob($mailbox->id, WarmupMailbox::factory()->create()->id);
        $job->failed(new RuntimeException('SMTP failed'));

        $mailbox->refresh();
        $this->assertSame('failed', $mailbox->status);
        $this->assertFalse($mailbox->warmup_enabled);
        $this->assertSame(3, $mailbox->consecutive_failures);
        $this->assertDatabaseHas('warmup_alerts', [
            'warmup_mailbox_id' => $mailbox->id,
            'type' => 'connection_failed',
        ]);
    }

    public function test_send_job_resets_consecutive_failures_on_success(): void
    {
        Bus::fake([ProcessWarmupInboxJob::class]);

        $mailbox = WarmupMailbox::factory()->outreach()->warming()->create([
            'consecutive_failures' => 2,
        ]);
        $seed = WarmupMailbox::factory()->create();

        $sendService = Mockery::mock(WarmupSendService::class);
        $sendService->shouldReceive('sendWarmupEmail')->once();

        (new SendWarmupEmailJob($mailbox->id, $seed->id))->handle($sendService);

        $this->assertSame(0, $mailbox->fresh()->consecutive_failures);
    }

    public function test_inbox_job_marks_outbox_failed_after_three_consecutive_failures(): void
    {
        $mailbox = WarmupMailbox::factory()->outreach()->warming()->create([
            'consecutive_failures' => 2,
        ]);

        $job = new ProcessWarmupInboxJob($mailbox->id, WarmupMailbox::factory()->create()->id);
        $job->failed(new RuntimeException('IMAP failed'));

        $mailbox->refresh();
        $this->assertSame('failed', $mailbox->status);
        $this->assertFalse($mailbox->warmup_enabled);
        $this->assertDatabaseHas('warmup_alerts', [
            'warmup_mailbox_id' => $mailbox->id,
            'type' => 'connection_failed',
        ]);
    }

    public function test_inbox_job_resets_consecutive_failures_on_success(): void
    {
        $mailbox = WarmupMailbox::factory()->outreach()->warming()->create([
            'consecutive_failures' => 1,
        ]);
        $seed = WarmupMailbox::factory()->create();

        $sendService = Mockery::mock(WarmupSendService::class);
        $sendService->shouldReceive('processInbox')->once()->withArgs(
            fn (WarmupMailbox $mailbox) => $mailbox->id === $seed->id,
        );

        (new ProcessWarmupInboxJob($mailbox->id, $seed->id))->handle($sendService);

        $this->assertSame(0, $mailbox->fresh()->consecutive_failures);
    }

    public function test_send_job_schedules_inbox_check_two_hours_after_send(): void
    {
        Bus::fake([ProcessWarmupInboxJob::class]);

        Carbon::setTestNow('2026-06-18 10:00:00');

        $mailbox = WarmupMailbox::factory()->outreach()->warming()->create();
        $seed = WarmupMailbox::factory()->create();

        $sendService = Mockery::mock(WarmupSendService::class);
        $sendService->shouldReceive('sendWarmupEmail')->once();

        (new SendWarmupEmailJob($mailbox->id, $seed->id))->handle($sendService);

        Bus::assertDispatched(ProcessWarmupInboxJob::class, function (ProcessWarmupInboxJob $job) use ($mailbox, $seed) {
            $delay = $job->delay;
            $runAt = $delay instanceof \DateTimeInterface
                ? Carbon::instance($delay)
                : now()->add($delay);

            return $job->outboxId === $mailbox->id
                && $job->seedId === $seed->id
                && $runAt->equalTo(Carbon::parse('2026-06-18 12:00:00'));
        });
    }

    public function test_inbox_job_dispatches_reply_for_newly_opened_sends(): void
    {
        Bus::fake([ReplyToWarmupEmailJob::class]);

        $mailbox = WarmupMailbox::factory()->outreach()->warming()->create();
        $seed = WarmupMailbox::factory()->create();

        $sendService = Mockery::mock(WarmupSendService::class);
        $sendService->shouldReceive('processInbox')->once()->andReturn([42]);

        (new ProcessWarmupInboxJob($mailbox->id, $seed->id))->handle($sendService);

        Bus::assertDispatched(ReplyToWarmupEmailJob::class, function (ReplyToWarmupEmailJob $job) use ($seed) {
            return $job->sendId === 42 && $job->fromMailboxId === $seed->id;
        });
    }

    public function test_connection_failed_alert_message_does_not_contain_raw_exception(): void
    {
        $mailbox = WarmupMailbox::factory()->outreach()->warming()->create([
            'consecutive_failures' => 2,
        ]);

        $job = new SendWarmupEmailJob($mailbox->id, WarmupMailbox::factory()->create()->id);
        $job->failed(new RuntimeException('smtp://user:SecretPass@host:587 connection refused'));

        $alert = WarmupAlert::query()->where('warmup_mailbox_id', $mailbox->id)->first();
        $this->assertStringNotContainsString('SecretPass', $alert->message);
    }
}
