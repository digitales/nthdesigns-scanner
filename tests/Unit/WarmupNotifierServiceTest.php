<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\WarmupMailbox;
use App\Notifications\WarmupMailboxNotification;
use App\Services\Warmup\WarmupNotifierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WarmupNotifierServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_alert_and_notification_once_per_type(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $mailbox = WarmupMailbox::factory()->outreach()->create([
            'user_id' => $user->id,
        ]);

        $service = app(WarmupNotifierService::class);
        $service->notify($mailbox, 'ready', 'Mailbox is ready.');
        $service->notify($mailbox, 'ready', 'Mailbox is ready.');

        $this->assertDatabaseCount('warmup_alerts', 1);
        Notification::assertSentTo($user, WarmupMailboxNotification::class, 1);
    }
}
