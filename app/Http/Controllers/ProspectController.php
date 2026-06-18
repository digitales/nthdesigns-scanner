<?php

namespace App\Http\Controllers;

use App\Actions\DispatchMarketScanRefresh;
use App\Enums\OutreachChannel;
use App\Http\Requests\UpdateProspectRequest;
use App\Http\Resources\ProspectShowResource;
use App\Jobs\GenerateOutreachEmailJob;
use App\Jobs\GenerateProspectReportJob;
use App\Jobs\QualifyProspectJob;
use App\Models\Prospect;
use App\Services\AgencyBookingService;
use App\Services\CombineScoresService;
use App\Services\ProgressFlowService;
use App\Services\Outreach\OutreachChannelResolver;
use App\Services\ProspectAuditService;
use App\Services\ProspectEnrichmentService;
use App\Services\ProspectExclusionService;
use App\Services\ProspectListMembershipService;
use App\Services\ProspectUnsubscribeService;
use App\Services\ReportBuilderService;
use App\Services\TagService;
use Illuminate\Http\JsonResponse;
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
        ProspectListMembershipService $listMembership,
        TagService $tags,
        AgencyBookingService $agencyBooking,
        ProspectUnsubscribeService $unsubscribe,
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

        return Inertia::render('Prospect/Show', ProspectShowResource::format(
            $request,
            $prospect,
            $reportBuilder,
            $exclusions,
            $progressFlow,
            $combiner,
            $listMembership,
            $tags,
            $agencyBooking,
            $unsubscribe,
        ));
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

    public function generateOutreach(
        Request $request,
        Prospect $prospect,
        ProspectUnsubscribeService $unsubscribe,
        OutreachChannelResolver $channels,
    ): RedirectResponse {
        $this->authorize('view', $prospect);

        $resolved = $channels->channelsFor($prospect);

        if ($resolved === []) {
            return back()->withErrors([
                'outreach' => 'No outreach path available. Add an email or confirm form outreach.',
            ]);
        }

        $queued = 0;

        foreach ($resolved as $channel) {
            if ($channel === OutreachChannel::Email
                && $unsubscribe->outreachSkipReason($request->user(), $prospect) !== null) {
                continue;
            }

            GenerateOutreachEmailJob::dispatch($prospect, $request->user(), [], $channel);
            $queued++;
        }

        if ($queued === 0) {
            return back()->withErrors([
                'outreach' => 'This email is unsubscribed and cannot receive outreach.',
            ]);
        }

        return back()->with('success', 'Outreach generation started. Refresh in a few seconds.');
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

    public function qualify(Prospect $prospect): JsonResponse
    {
        $this->authorize('view', $prospect);

        $force = request()->boolean('force');

        if (! $force && $prospect->qualification_ran_at && $prospect->qualification_ran_at->gt(now()->subMinutes(5))) {
            return response()->json(['message' => 'Qualification recently completed.'], 200);
        }

        QualifyProspectJob::dispatch($prospect);

        return response()->json(['message' => 'Qualification queued.'], 202);
    }
}
