<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProspectValidationSignal extends Model
{
    protected $fillable = [
        'pattern',
        'label',
        'active',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (ProspectValidationSignal $signal): void {
            $signal->pattern = strtolower(trim($signal->pattern));
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
