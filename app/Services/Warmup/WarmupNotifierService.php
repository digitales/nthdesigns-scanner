<?php

namespace App\Services\Warmup;

use App\Models\WarmupAlert;
use App\Models\WarmupMailbox;
use App\Notifications\WarmupMailboxNotification;

class WarmupNotifierService
{
    public function notify(WarmupMailbox $mailbox, string $type, string $message): void
    {
        $hasUnreadAlert = $mailbox->alerts()
            ->where('type', $type)
            ->whereNull('read_at')
            ->exists();

        if ($hasUnreadAlert) {
            return;
        }

        WarmupAlert::create([
            'warmup_mailbox_id' => $mailbox->id,
            'type' => $type,
            'message' => $message,
            'created_at' => now(),
        ]);

        $mailbox->loadMissing('user');
        $mailbox->user?->notify(new WarmupMailboxNotification($mailbox, $type, $message));
    }
}
