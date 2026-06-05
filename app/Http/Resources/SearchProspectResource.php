<?php

namespace App\Http\Resources;

use App\Models\Prospect;
use App\Models\Search;
use App\Services\ProgressFlowService;
use App\Services\ReportBuilderService;

class SearchProspectResource
{
    /**
     * @return array<string, mixed>
     */
    public static function forMcp(
        Prospect $prospect,
        Search $search,
        ReportBuilderService $reportBuilder,
        ProgressFlowService $progressFlow,
    ): array {
        $cms = $reportBuilder->cmsForProspect($prospect);

        return [
            'id' => $prospect->id,
            'business_name' => $prospect->business_name,
            'combined_score' => $prospect->combined_score,
            'gbp_score' => $prospect->gbp_score,
            'a11y_score' => $prospect->a11y_score,
            'performance_score' => $prospect->performance_score,
            'dominant_angle' => $prospect->dominant_angle,
            'audit_status' => $prospect->audit_status,
            'audit_error' => self::auditError($prospect),
            'gbp_flags' => $prospect->gbp_flags ?? [],
            'a11y_flags' => $prospect->a11y_flags ?? [],
            'report_ready' => $prospect->report !== null,
            'cms_badge' => $cms['badge'] ?? null,
            'cms_pending' => $cms['pending'] ?? false,
            'progress_flow' => $progressFlow->prospectFlow($prospect, $search),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function format(
        Prospect $prospect,
        Search $search,
        ReportBuilderService $reportBuilder,
        ProgressFlowService $progressFlow,
    ): array {
        $latestOutreach = $prospect->outreachEmails->first();
        $cms = $reportBuilder->cmsForProspect($prospect);

        return [
            'id' => $prospect->id,
            'business_name' => $prospect->business_name,
            'address' => $prospect->address,
            'phone' => $prospect->phone,
            'website_url' => $prospect->website_url,
            'place_id' => $prospect->place_id,
            'rating' => $prospect->rating,
            'review_count' => $prospect->review_count,
            'photo_count' => $prospect->photo_count,
            'has_description' => $prospect->has_description,
            'hours_complete' => $prospect->hours_complete,
            'gbp_score' => $prospect->gbp_score,
            'gbp_flags' => $prospect->gbp_flags ?? [],
            'a11y_score' => $prospect->a11y_score,
            'a11y_flags' => $prospect->a11y_flags ?? [],
            'performance_score' => $prospect->performance_score,
            'combined_score' => $prospect->combined_score,
            'dominant_angle' => $prospect->dominant_angle,
            'audit_status' => $prospect->audit_status,
            'audit_error' => self::auditError($prospect),
            'report_ready' => $prospect->report !== null,
            'report_url' => $prospect->report ? url('/r/'.$prospect->report->token) : null,
            'is_warm' => $prospect->report?->viewed_at !== null
                && $latestOutreach?->sent_at !== null
                && ! ($latestOutreach?->response_received ?? false),
            'last_viewed' => $prospect->report?->viewed_at?->diffForHumans(),
            'cms_badge' => $cms['badge'] ?? null,
            'cms_pending' => $cms['pending'] ?? false,
            'progress_flow' => $progressFlow->prospectFlow($prospect, $search),
        ];
    }

    private static function auditError(Prospect $prospect): ?string
    {
        $auditJob = $prospect->auditJobs->firstWhere('status', 'failed')
            ?? $prospect->auditJobs->first();

        return $auditJob?->error_message;
    }
}
