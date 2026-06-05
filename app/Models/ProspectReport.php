<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProspectReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'prospect_id', 'token', 'benchmark_place_id', 'screenshot_paths',
        'report_data', 'viewed_at', 'view_count', 'viewer_ip', 'expires_at',
    ];

    protected $casts = [
        'screenshot_paths' => 'array',
        'report_data' => 'array',
        'viewed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class);
    }

    public function booking(): HasOne
    {
        return $this->hasOne(ReportBooking::class);
    }
}
