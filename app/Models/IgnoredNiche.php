<?php

namespace App\Models;

use App\Enums\IgnoredNicheReason;
use Illuminate\Database\Eloquent\Model;

class IgnoredNiche extends Model
{
    protected $fillable = [
        'niche',
        'reason',
    ];

    protected $casts = [
        'reason' => IgnoredNicheReason::class,
    ];
}
