<?php

namespace App\Models;

use App\Enums\OutreachChannel;
use App\Enums\PitchAngle;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutreachEmail extends Model
{
    use HasFactory;

    protected $fillable = [
        'prospect_id', 'user_id', 'prospect_report_id', 'pitch_angle', 'channel',
        'cpc_benchmark', 'cpc_source',
        'subject_line', 'email_body', 'model_used', 'prompt_tokens',
        'completion_tokens', 'sent_at', 'response_received',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'response_received' => 'boolean',
            'pitch_angle' => PitchAngle::class,
            'channel' => OutreachChannel::class,
            'cpc_benchmark' => 'decimal:2',
        ];
    }

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
