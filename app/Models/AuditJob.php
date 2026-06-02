<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AuditJob extends Model
{
    protected $fillable = [
        'prospect_id', 'job_type', 'status', 'attempts',
        'error_message', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class);
    }

    public function errorDetail(): HasOne
    {
        return $this->hasOne(AuditJobErrorDetail::class);
    }
}
