<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportBooking extends Model
{
    protected $fillable = [
        'prospect_report_id',
        'prospect_id',
        'starts_at',
        'ends_at',
        'attendee_name',
        'attendee_email',
        'attendee_phone',
        'note',
        'calendar_event_uid',
        'status',
        'confirmation_sent_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'confirmation_sent_at' => 'datetime',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(ProspectReport::class, 'prospect_report_id');
    }

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class);
    }
}
