<?php

namespace App\Models;

use App\Enums\AuditStatus;
use App\Enums\DominantAngle;
use App\Enums\ProspectFinancialStatus;
use App\Enums\ProspectOutreachChannel;
use App\Enums\ProspectValidatorStatus;
use App\Enums\UseFormOutreach;
use App\Enums\WebsiteDiscoveryConfidence;
use App\Enums\WebsiteUrlSource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Prospect extends Model
{
    use HasFactory;

    protected $fillable = [
        'search_id', 'place_id', 'business_name', 'phone', 'email', 'linkedin_url', 'contact_page_url',
        'use_form_outreach', 'outreach_channel', 'website_url', 'website_url_source',
        'website_discovery_confidence', 'website_discovered_at', 'address',
        'rating', 'review_count', 'photo_count', 'has_description', 'hours_complete',
        'gbp_score', 'gbp_flags', 'a11y_score', 'a11y_flags', 'performance_score',
        'combined_score', 'dominant_angle', 'audit_status', 'suppress_auto_report',
        'raw_gbp_payload', 'raw_a11y_payload', 'raw_lighthouse_payload', 'cms_detection', 'contact_signals',
        'qualification_status', 'qualification_summary', 'qualification_flags', 'qualification_ran_at',
        'validator_status', 'validator_summary', 'validator_flags', 'validator_ran_at',
        'validator_override_status', 'validator_override_note', 'validator_override_by', 'validator_override_at',
        'companies_house_number', 'companies_house_status', 'companies_house_summary',
        'companies_house_flags', 'raw_companies_house_payload', 'companies_house_checked_at',
        'registered_company_name', 'registered_company_number', 'registered_company_note',
        'registered_company_by', 'registered_company_at',
        'registered_company_cleared_by', 'registered_company_cleared_at',
        'companies_house_details', 'companies_house_details_loaded_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'gbp_flags' => 'array',
            'a11y_flags' => 'array',
            'raw_gbp_payload' => 'array',
            'raw_a11y_payload' => 'array',
            'raw_lighthouse_payload' => 'array',
            'cms_detection' => 'array',
            'contact_signals' => 'array',
            'qualification_flags' => 'array',
            'qualification_ran_at' => 'datetime',
            'validator_flags' => 'array',
            'validator_ran_at' => 'datetime',
            'validator_status' => ProspectValidatorStatus::class,
            'validator_override_status' => ProspectValidatorStatus::class,
            'validator_override_at' => 'datetime',
            'companies_house_flags' => 'array',
            'raw_companies_house_payload' => 'array',
            'companies_house_checked_at' => 'datetime',
            'registered_company_at' => 'datetime',
            'registered_company_cleared_at' => 'datetime',
            'companies_house_details' => 'array',
            'companies_house_details_loaded_at' => 'datetime',
            'companies_house_status' => ProspectFinancialStatus::class,
            'has_description' => 'boolean',
            'hours_complete' => 'boolean',
            'rating' => 'decimal:1',
            'expires_at' => 'datetime',
            'website_discovered_at' => 'datetime',
            'suppress_auto_report' => 'boolean',
            'audit_status' => AuditStatus::class,
            'dominant_angle' => DominantAngle::class,
            'website_url_source' => WebsiteUrlSource::class,
            'website_discovery_confidence' => WebsiteDiscoveryConfidence::class,
            'use_form_outreach' => UseFormOutreach::class,
            'outreach_channel' => ProspectOutreachChannel::class,
        ];
    }

    public function isHighChance(): bool
    {
        return $this->validator_status === ProspectValidatorStatus::HighChance;
    }

    public function isLowChance(): bool
    {
        return $this->validator_status === ProspectValidatorStatus::LowChance;
    }

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

    public function reportBookings(): HasMany
    {
        return $this->hasMany(ReportBooking::class);
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

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'prospect_tag_assignments');
    }

    public function listItems(): HasMany
    {
        return $this->hasMany(ProspectListItem::class);
    }

    public function registeredCompanyBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_company_by');
    }

    public function registeredCompanyClearedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_company_cleared_by');
    }
}
