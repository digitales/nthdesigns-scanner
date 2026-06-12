<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketCpcDefault extends Model
{
    protected $fillable = [
        'user_id',
        'niche',
        'city',
        'country',
        'cpc_benchmark',
        'cpc_source',
        'cpc_keywords',
        'cpc_geo_target',
    ];

    protected function casts(): array
    {
        return [
            'cpc_benchmark' => 'decimal:2',
            'cpc_keywords' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
