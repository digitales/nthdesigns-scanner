<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NicheTagAssignment extends Model
{
    protected $fillable = ['user_id', 'tag_id', 'niche_label', 'city'];

    public function tag(): BelongsTo
    {
        return $this->belongsTo(Tag::class);
    }

    public function isGlobal(): bool
    {
        return $this->city === null;
    }
}
