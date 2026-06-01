<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserMcpKey extends Model
{
    protected $fillable = [
        'user_id',
        'key_hash',
        'label',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function hashKey(string $plainKey): string
    {
        return hash_hmac('sha256', $plainKey, config('app.key'));
    }

    public static function findByPlainKey(string $plainKey): ?self
    {
        return self::query()->where('key_hash', self::hashKey($plainKey))->first();
    }
}
