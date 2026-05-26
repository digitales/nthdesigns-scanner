<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Search extends Model
{
    protected $fillable = [
        'user_id', 'niche', 'city', 'country', 'scan_type', 'status', 'total_found',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function prospects(): HasMany
    {
        return $this->hasMany(Prospect::class);
    }
}
