<?php

namespace App\Models;

use App\Enums\ProspectListType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProspectList extends Model
{
    protected $fillable = ['user_id', 'name', 'type', 'description', 'filter'];

    protected function casts(): array
    {
        return [
            'type' => ProspectListType::class,
            'filter' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProspectListItem::class);
    }

    public function sharedLists(): HasMany
    {
        return $this->hasMany(SharedList::class);
    }

    public function isManual(): bool
    {
        return $this->type === ProspectListType::Manual;
    }

    public function isSmart(): bool
    {
        return $this->type === ProspectListType::Smart;
    }
}
