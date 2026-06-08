<?php

namespace App\Models;

use App\Enums\IgnoredProspectReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IgnoredProspect extends Model
{
    protected $fillable = [
        'user_id',
        'place_id',
        'reason',
        'note',
    ];

    protected $casts = [
        'reason' => IgnoredProspectReason::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        return $this->reason->label();
    }
}
