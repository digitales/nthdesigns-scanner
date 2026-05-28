<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProspectRequest;
use App\Jobs\GenerateOutreachEmailJob;
use App\Jobs\GenerateProspectReportJob;
use App\Models\Prospect;
use App\Services\ProspectEnrichmentService;
use App\Services\ReportBuilderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProspectController extends Controller
{
    public function show(Prospect $prospect, ReportBuilderService $reportBuilder): Response
    {
        $this->authorize('view', $prospect);

        $prospect->load([
            'search',
            'report',
            'outreachEmails' => fn ($q) => $q->latest(),
            'auditJobs',
            'notes.user',
        ]);

        return Inertia::render('Prospect/Show', [
            'prospect' => [
                'id'               => $prospect->id,
                'place_id'         => $prospect->place_id,
                'business_name'    => $prospect->business_name,
                'address'          => $prospect->address,
                'phone'            => $prospect->phone,
                'website_url'      => $prospect->website_url,
                'rating'           => $prospect->rating,
                'review_count'     => $prospect->review_count,
                'photo_count'      => $prospect->photo_count,
                'gbp_score'        => $prospect->gbp_score,
                'gbp_flags'        => $prospect->gbp_flags ?? [],
                'a11y_score'       => $prospect->a11y_score,
                'a11y_flags'       => $prospect->a11y_flags ?? [],
                'performance_score'=> $prospect->performance_score,
                'combined_score'   => $prospect->combined_score,
                'dominant_angle'   => $prospect->dominant_angle,
                'audit_status'     => $prospect->audit_status,
            ],
            'search' => [
                'id'        => $prospect->search->id,
                'niche'     => $prospect->search->niche,
                'city'      => $prospect->search->city,
                'scan_type' => $prospect->search->scan_type,
            ],
            'report' => $prospect->report ? [
                'id'               => $prospect->report->id,
                'token'            => $prospect->report->token,
                'public_url'       => url('/r/'.$prospect->report->token),
                'screenshot_paths' => $prospect->report->screenshot_paths ?? [],
                'view_count'       => $prospect->report->view_count,
                'expires_at'       => $prospect->report->expires_at?->toISOString(),
            ] : null,
            'outreachEmails' => $prospect->outreachEmails->map(fn ($e) => [
                'id'                 => $e->id,
                'pitch_angle'        => $e->pitch_angle,
                'subject_line'       => $e->subject_line,
                'email_body'         => $e->email_body,
                'model_used'         => $e->model_used,
                'sent_at'            => $e->sent_at?->toISOString(),
                'response_received'  => $e->response_received,
                'created_at'         => $e->created_at->diffForHumans(),
            ]),
            'audit' => $reportBuilder->buildOperatorAudit($prospect),
            'lighthouse' => $reportBuilder->lighthouseForProspect($prospect),
            'notes' => $prospect->notes->map(fn ($n) => [
                'id'         => $n->id,
                'body'       => $n->body,
                'author'     => $n->user?->name ?? 'You',
                'created_at' => $n->created_at->diffForHumans(),
            ]),
        ]);
    }

    public function update(UpdateProspectRequest $request, Prospect $prospect, ProspectEnrichmentService $enrichment): RedirectResponse
    {
        $result = $enrichment->update($prospect, $request->validated());

        $message = $result['audit_queued']
            ? 'Details saved. Site audit queued.'
            : 'Details saved.';

        return back()->with('success', $message);
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
}
