<?php

namespace App\Http\Resources;

use App\Enums\AuditJobStatus;
use App\Enums\AuditJobType;
use App\Enums\AuditStatus;
use App\Enums\IgnoredProspectReason;
use App\Enums\NicheScanStatus;
use App\Models\AuditJob;
use App\Models\IgnoredProspect;
use App\Models\NicheScan;
use App\Models\Prospect;
use App\Models\Search;
use App\Queries\LatestNicheScanQuery;
use App\Services\AgencyBookingService;
use App\Services\Booking\BookingPresentation;
use App\Services\CombineScoresService;
use App\Services\ProgressFlowService;
use App\Services\ProspectExclusionService;
use App\Services\ProspectListMembershipService;
use App\Services\ProspectUnsubscribeService;
use App\Services\ReportBuilderService;
use App\Services\TagService;
use App\Support\ProspectSiteScan;
use App\Support\QualificationFlagFormatter;
use Illuminate\Http\Request;

class ProspectShowResource
{
    /**
     * @return array<string, mixed>
     */
    public static function format(
        Request $request,
        Prospect $prospect,
        ReportBuilderService $reportBuilder,
        ProspectExclusionService $exclusions,
        ProgressFlowService $progressFlow,
        CombineScoresService $combiner,
        ProspectListMembershipService $listMembership,
        TagService $tags,
        AgencyBookingService $agencyBooking,
        ProspectUnsubscribeService $unsubscribe,
    ): array {
        $user = $request->user();
        $search = $prospect->search;
        $ignored = $exclusions->findForUser($user->id, $prospect->place_id);
        $emailSuppressed = $unsubscribe->isSuppressed($user, $prospect->email);
        $listMemberships = $listMembership->membershipsForProspect($user, $prospect->id);
        $manualLists = $listMembership->manualListsFor($user);

        return [
            'navigation' => self::navigation($request, $prospect, $search),
            'prospect' => self::prospect($prospect, $emailSuppressed),
            'search' => self::search($prospect, $search, $combiner),
            'report' => self::report($prospect, $agencyBooking),
            'outreachEmails' => self::outreachEmails($prospect),
            'inOutreach' => $user->outreachSelections()
                ->where('prospect_id', $prospect->id)
                ->exists(),
            'auditFailure' => self::auditFailureFor($prospect),
            'audit' => $reportBuilder->buildOperatorAudit($prospect),
            'cms' => $reportBuilder->cmsForProspect($prospect),
            'pageSpeed' => $reportBuilder->buildOperatorPageSpeed($prospect),
            'lighthouse' => $reportBuilder->lighthouseForProspect($prospect),
            'notes' => self::notes($prospect),
            'ignored' => self::ignored($ignored),
            'ignoreReasons' => collect(IgnoredProspectReason::cases())
                ->map(fn (IgnoredProspectReason $reason) => [
                    'value' => $reason->value,
                    'label' => $reason->label(),
                ])
                ->values()
                ->all(),
            'progress_flow' => $progressFlow->prospectFlow($prospect, $search),
            'marketScan' => self::marketScanFor($search),
            'tags' => self::tags($prospect),
            'tagSuggestions' => $tags->suggestionsFor($user),
            'listMembership' => $listMemberships,
            'addableLists' => $listMembership->addableLists($manualLists, $listMemberships),
        ];
    }

    /**
     * @return array{back_href: string, back_label: string}
     */
    private static function navigation(Request $request, Prospect $prospect, Search $search): array
    {
        return match ($request->query('from')) {
            'outreach' => ['back_href' => '/outreach', 'back_label' => 'Back to outreach'],
            'list' => [
                'back_href' => '/lists/'.$request->query('list_id'),
                'back_label' => 'Back to list',
            ],
            default => [
                'back_href' => '/searches/'.$search->id,
                'back_label' => $search->isDirectUrl()
                    ? 'Back to single site'
                    : 'Back to '.$search->niche,
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function prospect(Prospect $prospect, bool $emailSuppressed): array
    {
        return [
            'id' => $prospect->id,
            'place_id' => $prospect->place_id,
            'business_name' => $prospect->business_name,
            'address' => $prospect->address,
            'phone' => $prospect->phone,
            'email' => $prospect->email,
            'email_suppressed' => $emailSuppressed,
            'linkedin_url' => $prospect->linkedin_url,
            'contact_page_url' => $prospect->contact_page_url,
            'use_form_outreach' => $prospect->use_form_outreach?->value ?? 'auto',
            'outreach_channel' => $prospect->outreach_channel?->value ?? 'auto',
            'contact_signals' => $prospect->contact_signals,
            'website_url' => $prospect->website_url,
            'website_url_source' => $prospect->website_url_source ?? 'gbp',
            'website_discovery_confidence' => $prospect->website_discovery_confidence,
            'rating' => $prospect->rating,
            'review_count' => $prospect->review_count,
            'photo_count' => $prospect->photo_count,
            'gbp_score' => $prospect->gbp_score,
            'gbp_flags' => $prospect->gbp_flags ?? [],
            'a11y_score' => $prospect->a11y_score,
            'a11y_flags' => $prospect->a11y_flags ?? [],
            'performance_score' => $prospect->performance_score,
            'combined_score' => $prospect->combined_score,
            'dominant_angle' => $prospect->dominant_angle,
            'audit_status' => $prospect->audit_status,
            'site_unreachable' => ProspectSiteScan::siteUnreachable($prospect),
            'qualification_status' => $prospect->qualification_status,
            'qualification_summary' => $prospect->qualification_summary,
            'qualification_flags' => QualificationFlagFormatter::formatMany($prospect->qualification_flags ?? []),
            'qualification_ran_at' => $prospect->qualification_ran_at?->toISOString(),
            'validator_status' => $prospect->validator_status?->value,
            'validator_summary' => $prospect->validator_summary,
            'validator_flags' => $prospect->validator_flags ?? [],
            'validator_ran_at' => $prospect->validator_ran_at?->toISOString(),
            'validator_override_status' => $prospect->validator_override_status?->value,
            'validator_override_note' => $prospect->validator_override_note,
            'validator_override_at' => $prospect->validator_override_at?->toISOString(),
            'companies_house_number' => $prospect->companies_house_number,
            'companies_house_status' => $prospect->companies_house_status?->value,
            'companies_house_summary' => $prospect->companies_house_summary,
            'companies_house_flags' => $prospect->companies_house_flags ?? [],
            'companies_house_checked_at' => $prospect->companies_house_checked_at?->toISOString(),
            'companies_house_details' => $prospect->companies_house_details,
            'companies_house_details_loaded_at' => $prospect->companies_house_details_loaded_at?->toISOString(),
            'registered_company_name' => $prospect->registered_company_name,
            'registered_company_number' => $prospect->registered_company_number,
            'registered_company_note' => $prospect->registered_company_note,
            'registered_company_at' => $prospect->registered_company_at?->toISOString(),
            'registered_company_by_name' => $prospect->registeredCompanyBy?->name,
            'registered_company_cleared_at' => $prospect->registered_company_cleared_at?->toISOString(),
            'registered_company_cleared_by_name' => $prospect->registeredCompanyClearedBy?->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function search(Prospect $prospect, Search $search, CombineScoresService $combiner): array
    {
        return [
            'id' => $search->id,
            'source' => $search->source,
            'submitted_url' => $search->submitted_url,
            'niche' => $search->niche,
            'city' => $search->city,
            'scan_type' => $search->scan_type,
            'effective_scan_type' => $combiner->effectiveScanType($prospect, $search->scan_type),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function report(Prospect $prospect, AgencyBookingService $agencyBooking): ?array
    {
        if (! $prospect->report) {
            return null;
        }

        return [
            'id' => $prospect->report->id,
            'token' => $prospect->report->token,
            'public_url' => url('/r/'.$prospect->report->token),
            'screenshot_paths' => $prospect->report->screenshot_paths ?? [],
            'view_count' => $prospect->report->view_count,
            'expires_at' => $prospect->report->expires_at?->toISOString(),
            'booking' => $prospect->report->booking
                ? BookingPresentation::operatorBookingPayload(
                    $prospect->report->booking,
                    $agencyBooking->settings(),
                )
                : null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function outreachEmails(Prospect $prospect): array
    {
        return $prospect->outreachEmails->map(fn ($e) => [
            'id' => $e->id,
            'channel' => $e->channel?->value ?? 'email',
            'to_email' => $prospect->email,
            'contact_page_url' => $prospect->contact_page_url,
            'linkedin_url' => $prospect->linkedin_url,
            'pitch_angle' => $e->pitch_angle,
            'subject_line' => $e->subject_line,
            'email_body' => $e->email_body,
            'model_used' => $e->model_used,
            'sent_at' => $e->sent_at?->toISOString(),
            'response_received' => $e->response_received,
            'created_at' => $e->created_at->diffForHumans(),
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function notes(Prospect $prospect): array
    {
        return $prospect->notes->map(fn ($n) => [
            'id' => $n->id,
            'body' => $n->body,
            'author' => $n->user?->name ?? 'You',
            'created_at' => $n->created_at->diffForHumans(),
        ])->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function ignored(?IgnoredProspect $ignored): ?array
    {
        if (! $ignored) {
            return null;
        }

        return [
            'reason' => $ignored->reason,
            'reason_label' => $ignored->label(),
            'note' => $ignored->note,
            'ignored_at' => $ignored->updated_at->diffForHumans(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function tags(Prospect $prospect): array
    {
        return $prospect->tags->map(fn ($t) => [
            'id' => $t->id,
            'name' => $t->name,
            'color' => $t->color,
        ])->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function auditFailureFor(Prospect $prospect): ?array
    {
        if ($prospect->audit_status !== AuditStatus::Failed) {
            return null;
        }

        $failedJobs = $prospect->auditJobs
            ->where('status', AuditJobStatus::Failed)
            ->sortByDesc('id');

        $job = $failedJobs->firstWhere('job_type', AuditJobType::Accessibility)
            ?? $failedJobs->first();

        if (! $job instanceof AuditJob) {
            return null;
        }

        $detail = $job->errorDetail;

        return [
            'summary' => $job->error_message ?? 'Audit failed',
            'full' => $detail?->body,
            'detail_expired' => $detail === null,
            'job_id' => $job->id,
            'failed_at' => $job->completed_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function marketScanFor(Search $search): ?array
    {
        if ($search->isDirectUrl()) {
            return null;
        }

        $scan = LatestNicheScanQuery::ranked(
            fn ($query) => $query
                ->where('niche', $search->niche)
                ->where('city', $search->city),
        )->first();

        $scanDate = now('Europe/London')->toDateString();

        $isPending = NicheScan::query()
            ->where('niche', $search->niche)
            ->where('city', $search->city)
            ->whereDate('scan_date', $scanDate)
            ->where('status', NicheScanStatus::Pending)
            ->exists();

        return [
            'niche' => $search->niche,
            'city' => $search->city,
            'opportunity_score' => $scan?->opportunity_score,
            'result_count' => $scan?->result_count,
            'sampled_count' => $scan?->sampled_count,
            'status' => $scan?->status?->value,
            'ran_at_human' => $scan?->ran_at?->diffForHumans() ?? '—',
            'is_pending' => $isPending,
            'error_message' => $scan?->error_message,
            'niches_url' => '/niches?city='.urlencode($search->city),
        ];
    }
}
