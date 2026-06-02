<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditJobErrorDetail extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'audit_job_id',
        'body',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function auditJob(): BelongsTo
    {
        return $this->belongsTo(AuditJob::class);
    }
}
