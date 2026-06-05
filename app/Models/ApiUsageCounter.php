<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiUsageCounter extends Model
{
    protected $fillable = [
        'provider',
        'operation',
        'period_type',
        'period_key',
        'count',
    ];

    protected function casts(): array
    {
        return [
            'count' => 'integer',
        ];
    }
}
