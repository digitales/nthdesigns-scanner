<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateOutreachEmailJob;
use App\Models\OutreachEmail;
use App\Models\OutreachSelection;
use App\Models\Prospect;
use App\Services\UserSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OutreachController extends Controller
{
    public function __construct(private UserSettingsService $settings) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $selections = $user->outreachSelections()
            ->with(['prospect.search', 'prospect.report.booking', 'prospect.outreachEmails' => fn ($q) => $q->latest()])
            ->orderBy('created_at')
            ->get();

        $prospectIds = $selections->pluck('prospect_id');

        $emailsByProspect = OutreachEmail::query()
            ->where('user_id', $user->id)
            ->whereIn('prospect_id', $prospectIds)
            ->latest()
            ->get()
            ->groupBy('prospect_id')
            ->map(fn ($emails) => $emails->map(fn ($e) => [
                'id' => $e->id,
                'pitch_angle' => $e->pitch_angle,
                'subject_line' => $e->subject_line,
                'email_body' => $e->email_body,
                'sent_at' => $e->sent_at?->toISOString(),
                'response_received' => $e->response_received,
                'created_at' => $e->created_at->diffForHumans(),
            ])->values());

        return Inertia::render('Outreach/Index', [
            'selection' => $selections->map(fn (OutreachSelection $s) => [
                'id' => $s->id,
                'prospect_id' => $s->prospect_id,
                'business_name' => $s->prospect->business_name,
                'dominant_angle' => $s->prospect->dominant_angle,
                'combined_score' => $s->prospect->combined_score,
                'performance_score' => $s->prospect->performance_score,
                'report_ready' => $s->prospect->report !== null,
                'report_url' => $s->prospect->report ? url('/r/'.$s->prospect->report->token.'#book') : null,
                'booked_label' => $s->prospect->report?->booking
                    ? 'Booked · '.$s->prospect->report->booking->starts_at->format('j M g:ia')
                    : null,
            ]),
            'emailsByProspect' => $emailsByProspect,
            'defaults' => [
                'agency_name' => $this->settings->agencyName($user) ?? '',
                'pitch_angle' => 'auto',
                'cpc_benchmark' => '',
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'skipped' => $request->session()->get('skipped', []),
            ],
        ]);
    }

    public function storeSelection(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'prospect_ids' => 'required|array',
            'prospect_ids.*' => 'integer|exists:prospects,id',
        ]);

        foreach ($validated['prospect_ids'] as $prospectId) {
            $prospect = Prospect::findOrFail($prospectId);
            $this->authorize('view', $prospect);

            OutreachSelection::firstOrCreate([
                'user_id' => $request->user()->id,
                'prospect_id' => $prospect->id,
            ]);
        }

        return back();
    }

    public function destroySelection(Request $request, Prospect $prospect): RedirectResponse
    {
        $this->authorize('view', $prospect);

        OutreachSelection::query()
            ->where('user_id', $request->user()->id)
            ->where('prospect_id', $prospect->id)
            ->delete();

        return back();
    }

    public function clearSelections(Request $request): RedirectResponse
    {
        $request->user()->outreachSelections()->delete();

        return back();
    }

    public function generate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'agency_name' => 'nullable|string|max:100',
            'pitch_angle' => 'required|in:auto,gbp,accessibility,combined',
            'cpc_benchmark' => 'nullable|numeric|min:0',
        ]);

        $options = ['pitch_angle' => $validated['pitch_angle']];

        if (! empty($validated['agency_name'])) {
            $options['agency_name'] = $validated['agency_name'];
        }

        if (isset($validated['cpc_benchmark'])) {
            $options['cpc_benchmark'] = (float) $validated['cpc_benchmark'];
        }

        $selections = $request->user()->outreachSelections()->with('prospect.report')->get();
        $dispatched = 0;
        $skipped = [];

        foreach ($selections as $selection) {
            if (! $selection->prospect->report) {
                $skipped[] = $selection->prospect->business_name;

                continue;
            }

            GenerateOutreachEmailJob::dispatch(
                $selection->prospect,
                $request->user(),
                $options,
            );

            $dispatched++;
        }

        return back()->with([
            'success' => "{$dispatched} email(s) queued for generation.",
            'skipped' => $skipped,
        ]);
    }
}
