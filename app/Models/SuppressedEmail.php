<?php

namespace App\Models;

use App\Enums\SuppressionSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuppressedEmail extends Model
{
    protected $fillable = [
        'user_id',
        'email',
        'source',
        'prospect_id',
    ];

    protected function casts(): array
    {
        return [
            'source' => SuppressionSource::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class);
    }
}
