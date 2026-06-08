<?php

namespace App\Http\Controllers;

use App\Actions\DispatchMarketScanRefresh;
use App\Enums\AuditJobStatus;
use App\Enums\AuditJobType;
use App\Enums\AuditStatus;
use App\Enums\IgnoredProspectReason;
use App\Enums\NicheScanStatus;
use App\Enums\ProspectListType;
use App\Http\Requests\UpdateProspectRequest;
use App\Jobs\GenerateOutreachEmailJob;
use App\Jobs\GenerateProspectReportJob;
use App\Models\AuditJob;
use App\Models\NicheScan;
use App\Models\Prospect;
use App\Models\Search;
use App\Queries\LatestNicheScanQuery;
use App\Services\AgencyBookingService;
use App\Services\Booking\BookingPresentation;
use App\Services\CombineScoresService;
use App\Services\ProgressFlowService;
use App\Services\ProspectAuditService;
use App\Services\ProspectEnrichmentService;
use App\Services\ProspectExclusionService;
use App\Services\ReportBuilderService;
use App\Services\TagService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProspectController extends Controller
{
    public function show(
        Request $request,
        Prospect $prospect,
        ReportBuilderService $reportBuilder,
        ProspectExclusionService $exclusions,
        ProgressFlowService $progressFlow,
        CombineScoresService $combiner,
    ): Response {
        $this->authorize('view', $prospect);

        $prospect->load([
            'search',
            'report.booking',
            'outreachEmails' => fn ($q) => $q->latest(),
            'auditJobs.errorDetail',
            'notes.user',
            'tags',
        ]);

        $navigation = match ($request->query('from')) {
            'outreach' => ['back_href' => '/outreach', 'back_label' => 'Back to outreach'],
            'list' => [
                'back_href' => '/lists/'.$request->query('list_id'),
                'back_label' => 'Back to list',
            ],
            default => [
                'back_href' => '/searches/'.$prospect->search->id,
                'back_label' => $prospect->search->isDirectUrl()
                    ? 'Back to single site'
                    : 'Back to '.$prospect->search->niche,
            ],
        };

        $ignored = $exclusions->findForUser($request->user()->id, $prospect->place_id);

        return Inertia::render('Prospect/Show', [
            'navigation' => $navigation,
            'prospect' => [
                'id' => $prospect->id,
                'place_id' => $prospect->place_id,
                'business_name' => $prospect->business_name,
                'address' => $prospect->address,
                'phone' => $prospect->phone,
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
            ],
            'search' => [
                'id' => $prospect->search->id,
                'source' => $prospect->search->source,
                'submitted_url' => $prospect->search->submitted_url,
                'niche' => $prospect->search->niche,
                'city' => $prospect->search->city,
                'scan_type' => $prospect->search->scan_type,
                'effective_scan_type' => $combiner->effectiveScanType($prospect, $prospect->search->scan_type),
            ],
            'report' => $prospect->report ? [
                'id' => $prospect->report->id,
                'token' => $prospect->report->token,
                'public_url' => url('/r/'.$prospect->report->token),
                'screenshot_paths' => $prospect->report->screenshot_paths ?? [],
                'view_count' => $prospect->report->view_count,
                'expires_at' => $prospect->report->expires_at?->toISOString(),
                'booking' => $prospect->report->booking
                    ? BookingPresentation::operatorBookingPayload(
                        $prospect->report->booking,
                        app(AgencyBookingService::class)->settings(),
                    )
                    : null,
            ] : null,
            'outreachEmails' => $prospect->outreachEmails->map(fn ($e) => [
                'id' => $e->id,
                'pitch_angle' => $e->pitch_angle,
                'subject_line' => $e->subject_line,
                'email_body' => $e->email_body,
                'model_used' => $e->model_used,
                'sent_at' => $e->sent_at?->toISOString(),
                'response_received' => $e->response_received,
                'created_at' => $e->created_at->diffForHumans(),
            ]),
            'auditFailure' => $this->auditFailureFor($prospect),
            'audit' => $reportBuilder->buildOperatorAudit($prospect),
            'cms' => $reportBuilder->cmsForProspect($prospect),
            'pageSpeed' => $reportBuilder->buildOperatorPageSpeed($prospect),
            'lighthouse' => $reportBuilder->lighthouseForProspect($prospect),
            'notes' => $prospect->notes->map(fn ($n) => [
                'id' => $n->id,
                'body' => $n->body,
                'author' => $n->user?->name ?? 'You',
                'created_at' => $n->created_at->diffForHumans(),
            ]),
            'ignored' => $ignored ? [
                'reason' => $ignored->reason,
                'reason_label' => $ignored->label(),
                'note' => $ignored->note,
                'ignored_at' => $ignored->updated_at->diffForHumans(),
            ] : null,
            'ignoreReasons' => collect(IgnoredProspectReason::cases())
                ->map(fn (IgnoredProspectReason $reason) => [
                    'value' => $reason->value,
                    'label' => $reason->label(),
                ])
                ->values()
                ->all(),
            'progress_flow' => $progressFlow->prospectFlow($prospect, $prospect->search),
            'marketScan' => $this->marketScanFor($prospect->search),
            'tags' => $prospect->tags->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'color' => $t->color,
            ]),
            'tagSuggestions' => app(TagService::class)->suggestionsFor($request->user()),
            'manualLists' => $request->user()
                ->prospectLists()
                ->where('type', ProspectListType::Manual)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function auditFailureFor(Prospect $prospect): ?array
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

    public function update(UpdateProspectRequest $request, Prospect $prospect, ProspectEnrichmentService $enrichment): RedirectResponse
    {
        $result = $enrichment->update($prospect, $request->validated());

        $message = $result['audit_queued']
            ? 'Details saved. Site audit queued.'
            : 'Details saved.';

        return back()->with('success', $message);
    }

    public function reauditSite(Prospect $prospect, ProspectAuditService $audits): RedirectResponse
    {
        $this->authorize('view', $prospect);

        $audits->queueSiteAudit($prospect);

        return back()->with('success', 'Site audit queued. GBP scores unchanged.');
    }

    public function generateReport(Prospect $prospect): RedirectResponse
    {
        $this->authorize('view', $prospect);

        GenerateProspectReportJob::dispatch($prospect);

        return back()->with('success', 'Report generation started. Refresh in a few seconds.');
    }

    public function generateOutreach(Request $request, Prospect $prospect): RedirectResponse
    {
        $this->authorize('view', $prospect);

        GenerateOutreachEmailJob::dispatch($prospect, $request->user());

        return back()->with('success', 'Outreach email generation started. Refresh in a few seconds.');
    }

    public function refreshMarketScan(Request $request, Prospect $prospect, DispatchMarketScanRefresh $dispatch): RedirectResponse
    {
        $this->authorize('view', $prospect);

        $prospect->loadMissing('search');
        $search = $prospect->search;

        abort_if($search->isDirectUrl(), 422);

        $result = $dispatch(
            niche: $search->niche,
            city: $search->city,
            country: $search->country,
            userId: $request->user()->id,
        );

        if ($result->isAlreadyPending()) {
            return back()->with('success', 'Market scan already in progress.');
        }

        if ($result->isRateLimited()) {
            return back()->with('error', "Please wait {$result->rateLimitSeconds} seconds before refreshing this market scan.");
        }

        return back()->with('success', "Market scan queued for {$search->niche} in {$search->city}.");
    }

    /**
     * @return array<string, mixed>|null
     */
    private function marketScanFor(Search $search): ?array
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
