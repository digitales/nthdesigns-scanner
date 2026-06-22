<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarmupMailbox extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'email', 'provider', 'imap_host', 'imap_port',
        'smtp_host', 'smtp_port', 'username', 'password_encrypted',
        'is_outreach_mailbox', 'is_seed_mailbox', 'is_pool_participant',
        'warmup_enabled', 'warmup_started_at', 'warmup_target_volume',
        'warmup_ramp_days', 'send_window_start', 'send_window_end',
        'send_on_weekends', 'status', 'deliverability_score', 'last_imap_check_at',
        'consecutive_failures',
    ];

    protected function casts(): array
    {
        return [
            'is_outreach_mailbox' => 'boolean',
            'is_seed_mailbox' => 'boolean',
            'is_pool_participant' => 'boolean',
            'warmup_enabled' => 'boolean',
            'send_on_weekends' => 'boolean',
            'warmup_started_at' => 'datetime',
            'last_imap_check_at' => 'datetime',
        ];
    }

    public function setPasswordEncryptedAttribute(string $value): void
    {
        $this->attributes['password_encrypted'] = encrypt(self::normaliseAppPassword($value));
    }

    public static function normaliseAppPassword(string $password): string
    {
        return preg_replace('/\s+/', '', trim($password)) ?? trim($password);
    }

    public static function appPasswordValidationMessage(string $provider, string $password): ?string
    {
        if ($provider !== 'gmail') {
            return null;
        }

        if (strlen(self::normaliseAppPassword($password)) !== 16) {
            return 'Gmail app passwords are exactly 16 characters. Create one at myaccount.google.com/apppasswords — do not use your login password.';
        }

        return null;
    }

    public function getDecryptedPasswordAttribute(): string
    {
        return decrypt($this->password_encrypted);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sendsFrom(): HasMany
    {
        return $this->hasMany(WarmupSend::class, 'from_mailbox_id');
    }

    public function sendsTo(): HasMany
    {
        return $this->hasMany(WarmupSend::class, 'to_mailbox_id');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(WarmupAlert::class);
    }

    public static function statusForScore(int $score, int $daysWarming, int $rampDays, ?string $currentStatus = null): string
    {
        if ($score >= 80 && $daysWarming >= $rampDays) {
            return 'ready';
        }

        $hasWarmedBefore = in_array($currentStatus, ['warming', 'ready', 'at_risk'], true);

        if ($score < 50 && $hasWarmedBefore) {
            return 'at_risk';
        }

        return 'warming';
    }

    public function getDaysWarmingAttribute(): int
    {
        if (! $this->warmup_started_at) {
            return 0;
        }

        return (int) $this->warmup_started_at->diffInDays(now());
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('warmup_enabled', true)
            ->whereIn('status', ['warming', 'ready', 'at_risk']);
    }
}
