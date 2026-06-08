<?php

namespace App\Models;

use App\Enums\NicheScanStatus;
use Illuminate\Database\Eloquent\Model;

class NicheScan extends Model
{
    protected $fillable = [
        'niche',
        'niche_query',
        'city',
        'country',
        'scan_date',
        'result_count',
        'sampled_count',
        'sample_preview',
        'avg_gbp_score',
        'pct_no_website',
        'pct_low_reviews',
        'opportunity_score',
        'status',
        'error_message',
        'ran_at',
    ];

    protected function casts(): array
    {
        return [
            'scan_date' => 'date',
            'sample_preview' => 'array',
            'ran_at' => 'datetime',
            'avg_gbp_score' => 'float',
            'pct_no_website' => 'float',
            'pct_low_reviews' => 'float',
            'opportunity_score' => 'float',
            'status' => NicheScanStatus::class,
        ];
    }
}
