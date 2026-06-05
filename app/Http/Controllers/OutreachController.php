<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerateOutreachEmailRequest;
use App\Http\Requests\StoreOutreachSelectionRequest;
use App\Http\Resources\OutreachEmailResource;
use App\Http\Resources\OutreachSelectionResource;
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
            ->map(fn ($emails) => $emails
                ->map(fn (OutreachEmail $email) => OutreachEmailResource::format($email))
                ->values());

        return Inertia::render('Outreach/Index', [
            'selection' => $selections
                ->map(fn (OutreachSelection $selection) => OutreachSelectionResource::format($selection))
                ->values(),
            'emailsByProspect' => $emailsByProspect,
            'defaults' => [
                'agency_name' => $this->settings->agencyName($user) ?? '',
                'pitch_angle' => 'auto',
                'cpc_benchmark' => '',
            ],
        ]);
    }

    public function storeSelection(StoreOutreachSelectionRequest $request): RedirectResponse
    {
        $validated = $request->validated();

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
        $selection = OutreachSelection::query()
            ->where('user_id', $request->user()->id)
            ->where('prospect_id', $prospect->id)
            ->firstOrFail();

        $this->authorize('delete', $selection);
        $selection->delete();

        return back();
    }

    public function clearSelections(Request $request): RedirectResponse
    {
        $this->authorize('deleteAny', OutreachSelection::class);
        $request->user()->outreachSelections()->delete();

        return back();
    }

    public function generate(GenerateOutreachEmailRequest $request): RedirectResponse
    {
        $validated = $request->validated();

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
