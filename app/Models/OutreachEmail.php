<?php

namespace App\Models;

use App\Enums\OutreachChannel;
use App\Enums\OutreachSendSource;
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
        'subject_line', 'email_body', 'generated_subject', 'generated_body',
        'sent_subject', 'sent_body', 'from_mailbox_id', 'smtp_message_id', 'send_source',
        'model_used', 'prompt_tokens',
        'completion_tokens', 'sent_at', 'response_received',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'response_received' => 'boolean',
            'pitch_angle' => PitchAngle::class,
            'channel' => OutreachChannel::class,
            'send_source' => OutreachSendSource::class,
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

    public function fromMailbox(): BelongsTo
    {
        return $this->belongsTo(WarmupMailbox::class, 'from_mailbox_id');
    }

    public function wasEditedBeforeSend(): bool
    {
        if ($this->sent_body === null) {
            return $this->generated_body !== null
                && $this->email_body !== $this->generated_body;
        }

        return $this->generated_body !== null
            && $this->sent_body !== $this->generated_body;
    }
}
