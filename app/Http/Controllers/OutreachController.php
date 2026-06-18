<?php

namespace App\Http\Controllers;

use App\Enums\OutreachChannel;
use App\Http\Requests\GenerateOutreachEmailRequest;
use App\Http\Requests\StoreOutreachSelectionRequest;
use App\Http\Resources\OutreachSelectionResource;
use App\Jobs\GenerateOutreachEmailJob;
use App\Models\OutreachSelection;
use App\Models\Prospect;
use App\Services\Outreach\CpcBenchmarkResolver;
use App\Services\Outreach\OutreachChannelResolver;
use App\Services\Outreach\OutreachQueueLoader;
use App\Services\ProspectUnsubscribeService;
use App\Services\UserSettingsService;
use App\Services\Warmup\WarmupOutreachReadinessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OutreachController extends Controller
{
    public function __construct(
        private UserSettingsService $settings,
        private OutreachQueueLoader $queue,
        private CpcBenchmarkResolver $cpcBenchmarks,
        private ProspectUnsubscribeService $unsubscribe,
        private OutreachChannelResolver $channels,
        private WarmupOutreachReadinessService $warmupReadiness,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $bookedOnly = $request->boolean('booked');
        $selections = $this->queue->selections($user, $bookedOnly);
        $emailsByProspect = $this->queue->latestEmailsByProspect($user, $selections->pluck('prospect_id'));
        $cpcDefaults = $this->cpcBenchmarks->formDefaults($selections);

        return Inertia::render('Outreach/Index', [
            'selection' => $selections
                ->map(fn (OutreachSelection $selection) => OutreachSelectionResource::format($selection))
                ->values(),
            'filters' => ['booked' => $bookedOnly],
            'emailsByProspect' => $emailsByProspect,
            'defaults' => [
                'agency_name' => $this->settings->agencyName($user) ?? '',
                'pitch_angle' => 'auto',
                'cpc_benchmark' => $cpcDefaults['value'],
                'cpc_mixed' => $cpcDefaults['mixed'],
                'cpc_from_search' => $cpcDefaults['from_search'],
            ],
            'warmup_readiness' => $this->warmupReadiness->forUser($user),
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

        if (! empty($validated['cpc_benchmark'])) {
            $options['cpc_benchmark'] = (float) $validated['cpc_benchmark'];
            $options['cpc_source'] = 'outreach_override';
        }

        $selections = $this->queue->selections($request->user(), bookedOnly: false, withReport: true);
        $dispatched = 0;
        $skipped = [];

        foreach ($selections as $selection) {
            $prospect = $selection->prospect;

            if (! $prospect->report) {
                $skipped[] = $prospect->business_name.' (no report)';

                continue;
            }

            $channels = $this->channels->channelsFor($prospect);

            if ($channels === []) {
                $skipped[] = $prospect->business_name.' (no contact path)';

                continue;
            }

            $queuedForProspect = 0;

            foreach ($channels as $channel) {
                if ($channel === OutreachChannel::Email
                    && $this->unsubscribe->outreachSkipReason($request->user(), $prospect) !== null) {
                    continue;
                }

                GenerateOutreachEmailJob::dispatch(
                    $prospect,
                    $request->user(),
                    $options,
                    $channel,
                );

                $queuedForProspect++;
            }

            if ($queuedForProspect === 0) {
                $skipped[] = $prospect->business_name.' (unsubscribed)';

                continue;
            }

            $dispatched++;
        }

        return back()->with([
            'success' => "{$dispatched} prospect(s) queued for outreach generation.",
            'skipped' => $skipped,
        ]);
    }
}
