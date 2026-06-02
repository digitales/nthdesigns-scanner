<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IgnoredProspect extends Model
{
    public const REASON_ACQUIRED = 'acquired';

    public const REASON_COLD = 'cold';

    public const REASON_OUTREACH_FAILED = 'outreach_failed';

    public const REASON_OTHER = 'other';

    protected $fillable = [
        'user_id',
        'place_id',
        'reason',
        'note',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        return match ($this->reason) {
            self::REASON_ACQUIRED => 'Company acquired',
            self::REASON_COLD => 'Cold lead',
            self::REASON_OUTREACH_FAILED => 'Outreach did not work',
            default => 'Other',
        };
    }
}
