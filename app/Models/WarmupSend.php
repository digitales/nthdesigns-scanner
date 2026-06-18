<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarmupSend extends Model
{
    use HasFactory;

    protected $fillable = [
        'from_mailbox_id', 'to_mailbox_id', 'message_id', 'subject',
        'sent_at', 'opened_at', 'replied_at', 'rescued_from_spam_at', 'status',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'opened_at' => 'datetime',
            'replied_at' => 'datetime',
            'rescued_from_spam_at' => 'datetime',
        ];
    }

    public function fromMailbox(): BelongsTo
    {
        return $this->belongsTo(WarmupMailbox::class, 'from_mailbox_id');
    }

    public function toMailbox(): BelongsTo
    {
        return $this->belongsTo(WarmupMailbox::class, 'to_mailbox_id');
    }
}
