<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarmupAlert extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'warmup_mailbox_id',
        'type',
        'message',
        'created_at',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(WarmupMailbox::class, 'warmup_mailbox_id');
    }
}
