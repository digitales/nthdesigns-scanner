<?php

namespace App\Models;

use App\Enums\ScanType;
use App\Enums\SearchSource;
use App\Enums\SearchStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Search extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'source', 'submitted_url', 'niche', 'city', 'country', 'scan_type', 'status', 'total_found',
        'benchmark_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'status' => SearchStatus::class,
            'scan_type' => ScanType::class,
            'source' => SearchSource::class,
            'benchmark_snapshot' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function prospects(): HasMany
    {
        return $this->hasMany(Prospect::class);
    }

    public function isDirectUrl(): bool
    {
        return $this->source === SearchSource::DirectUrl;
    }
}
