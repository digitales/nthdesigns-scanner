<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Prospect extends Model
{
    use HasFactory;

    protected $fillable = [
        'search_id', 'place_id', 'business_name', 'phone', 'website_url', 'website_url_source',
        'website_discovery_confidence', 'website_discovered_at', 'address',
        'rating', 'review_count', 'photo_count', 'has_description', 'hours_complete',
        'gbp_score', 'gbp_flags', 'a11y_score', 'a11y_flags', 'performance_score',
        'combined_score', 'dominant_angle', 'audit_status', 'suppress_auto_report',
        'raw_gbp_payload', 'raw_a11y_payload', 'raw_lighthouse_payload', 'cms_detection', 'expires_at',
    ];

    protected $casts = [
        'gbp_flags'             => 'array',
        'a11y_flags'            => 'array',
        'raw_gbp_payload'       => 'array',
        'raw_a11y_payload'      => 'array',
        'raw_lighthouse_payload'=> 'array',
        'cms_detection'         => 'array',
        'has_description'       => 'boolean',
        'hours_complete'        => 'boolean',
        'rating'                => 'decimal:1',
        'expires_at'            => 'datetime',
        'website_discovered_at' => 'datetime',
        'suppress_auto_report'  => 'boolean',
    ];

    public function search(): BelongsTo
    {
        return $this->belongsTo(Search::class);
    }

    public function outreachEmails(): HasMany
    {
        return $this->hasMany(OutreachEmail::class);
    }

    public function report(): HasOne
    {
        return $this->hasOne(ProspectReport::class);
    }

    public function auditJobs(): HasMany
    {
        return $this->hasMany(AuditJob::class);
    }

    public function outreachSelections(): HasMany
    {
        return $this->hasMany(OutreachSelection::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ProspectNote::class)->latest();
    }
}
