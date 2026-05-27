<?php

namespace App\Models;

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
        'avg_gbp_score',
        'pct_no_website',
        'pct_low_reviews',
        'opportunity_score',
        'status',
        'ran_at',
    ];

    protected function casts(): array
    {
        return [
            'scan_date' => 'date',
            'ran_at' => 'datetime',
            'avg_gbp_score' => 'float',
            'pct_no_website' => 'float',
            'pct_low_reviews' => 'float',
            'opportunity_score' => 'float',
        ];
    }
}
