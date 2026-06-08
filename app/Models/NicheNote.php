<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NicheNote extends Model
{
    protected $fillable = ['user_id', 'niche_label', 'city', 'body'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isGlobal(): bool
    {
        return $this->city === null;
    }
}
