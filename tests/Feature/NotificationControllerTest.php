<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WarmupMailbox;
use App\Notifications\WarmupMailboxNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_mark_notification_read(): void
    {
        $user = User::factory()->create();
        $mailbox = WarmupMailbox::factory()->outreach()->create(['user_id' => $user->id]);

        $user->notify(new WarmupMailboxNotification($mailbox, 'ready', 'Ready to send.'));
        $notification = $user->fresh()->unreadNotifications->first();

        $this->actingAs($user)
            ->post("/notifications/{$notification->id}/read")
            ->assertRedirect();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_can_mark_all_notifications_read(): void
    {
        $user = User::factory()->create();
        $mailbox = WarmupMailbox::factory()->outreach()->create(['user_id' => $user->id]);

        $user->notify(new WarmupMailboxNotification($mailbox, 'ready', 'Ready to send.'));
        $user->notify(new WarmupMailboxNotification($mailbox, 'at_risk', 'At risk.'));

        $this->actingAs($user)
            ->post('/notifications/read-all')
            ->assertRedirect();

        $this->assertSame(0, $user->fresh()->unreadNotifications()->count());
    }
}
