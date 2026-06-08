<?php

namespace App\Models;

use App\Enums\ListItemStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProspectListItem extends Model
{
    protected $fillable = ['prospect_list_id', 'prospect_id', 'status', 'follow_up_at'];

    protected function casts(): array
    {
        return [
            'status' => ListItemStatus::class,
            'follow_up_at' => 'datetime',
        ];
    }

    public function list(): BelongsTo
    {
        return $this->belongsTo(ProspectList::class, 'prospect_list_id');
    }

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class);
    }
}
