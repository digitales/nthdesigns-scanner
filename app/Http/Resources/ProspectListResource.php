<?php

namespace App\Http\Resources;

use App\Models\Prospect;
use App\Services\ReportBuilderService;

class ProspectListResource
{
    public static function format(Prospect $prospect): array
    {
        $latest = $prospect->outreachEmails->first();
        $cms = app(ReportBuilderService::class)->cmsForProspect($prospect);

        return [
            'id' => $prospect->id,
            'business_name' => $prospect->business_name,
            'niche' => $prospect->search->niche,
            'city' => $prospect->search->city,
            'country' => $prospect->search->country,
            'phone' => $prospect->phone,
            'website_url' => $prospect->website_url,
            'combined_score' => $prospect->combined_score,
            'gbp_score' => $prospect->gbp_score,
            'a11y_score' => $prospect->a11y_score,
            'performance_score' => $prospect->performance_score,
            'dominant_angle' => $prospect->dominant_angle,
            'gbp_flags' => $prospect->gbp_flags ?? [],
            'a11y_flags' => $prospect->a11y_flags ?? [],
            'report_url' => $prospect->report ? url('/r/'.$prospect->report->token) : null,
            'report_ready' => $prospect->report !== null,
            'report_viewed_at' => $prospect->report?->viewed_at?->toISOString(),
            'outreach_sent' => $latest?->sent_at?->toISOString(),
            'outreach_sent_label' => $latest?->sent_at?->format('j M'),
            'report_viewed_label' => $prospect->report?->viewed_at?->diffForHumans(),
            'response_received' => (bool) ($latest?->response_received ?? false),
            'is_warm' => $prospect->report?->viewed_at !== null
                && $latest?->sent_at !== null
                && ! ($latest?->response_received ?? false),
            'cms_badge' => $cms['badge'] ?? null,
            'cms_pending' => $cms['pending'] ?? false,
            'qualification_status' => $prospect->qualification_status,
            'qualification_summary' => $prospect->qualification_summary,
            'qualification_flags' => $prospect->qualification_flags ?? [],
            'qualification_ran_at' => $prospect->qualification_ran_at?->toISOString(),
            'tags' => $prospect->relationLoaded('tags')
                ? $prospect->tags->pluck('name')->values()->all()
                : [],
        ];
    }
}
