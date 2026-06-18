<?php

namespace App\Notifications;

use App\Models\WarmupMailbox;
use Illuminate\Notifications\Notification;

class WarmupMailboxNotification extends Notification
{
    public function __construct(
        public WarmupMailbox $mailbox,
        public string $alertType,
        public string $message,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'alert_type' => $this->alertType,
            'mailbox_id' => $this->mailbox->id,
            'mailbox_email' => $this->mailbox->email,
            'message' => $this->message,
            'url' => route('warmup.show', $this->mailbox),
        ];
    }
}
