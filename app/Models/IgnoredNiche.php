<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IgnoredNiche extends Model
{
    public const REASON_MANUAL = 'manual';

    public const REASON_LOW_RESULTS = 'low_results';

    protected $fillable = [
        'niche',
        'reason',
    ];
}
