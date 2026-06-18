<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProspectValidationSignalRequest;
use App\Http\Requests\UpdateProspectValidationSignalRequest;
use App\Jobs\RevalidateProspectsForSignalJob;
use App\Models\ProspectValidationSignal;
use App\Services\ProspectValidationRulesService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ValidationRulesController extends Controller
{
    public function index(ProspectValidationRulesService $rules): Response
    {
        $operatorSignals = ProspectValidationSignal::query()
            ->with('creator:id,name')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ProspectValidationSignal $signal) => [
                'id' => $signal->id,
                'pattern' => $signal->pattern,
                'label' => $signal->label,
                'notes' => $signal->notes,
                'active' => $signal->active,
                'created_by' => $signal->creator?->name ?? 'Unknown',
                'created_at' => $signal->created_at->diffForHumans(),
            ]);

        return Inertia::render('Settings/ValidationRules', [
            'builtInSignals' => $rules->builtInSignalPatterns(),
            'operatorSignals' => $operatorSignals,
        ]);
    }

    public function store(
        StoreProspectValidationSignalRequest $request,
        ProspectValidationRulesService $rules,
    ): RedirectResponse {
        $signal = ProspectValidationSignal::query()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        $rules->clearCache();
        RevalidateProspectsForSignalJob::dispatch($signal->id);

        return redirect()
            ->route('settings.validation-rules.index')
            ->with('success', "Signal \"{$signal->label}\" added. Re-validation queued.");
    }

    public function update(
        UpdateProspectValidationSignalRequest $request,
        ProspectValidationSignal $prospectValidationSignal,
        ProspectValidationRulesService $rules,
    ): RedirectResponse {
        $oldPattern = $prospectValidationSignal->pattern;
        $prospectValidationSignal->update($request->validated());
        $rules->clearCache();

        RevalidateProspectsForSignalJob::dispatch(
            $prospectValidationSignal->id,
            $oldPattern !== $prospectValidationSignal->pattern ? $oldPattern : null,
        );

        return redirect()
            ->route('settings.validation-rules.index')
            ->with('success', 'Validation signal updated. Re-validation queued.');
    }

    public function destroy(
        Request $request,
        ProspectValidationSignal $prospectValidationSignal,
        ProspectValidationRulesService $rules,
    ): RedirectResponse {
        $signalId = $prospectValidationSignal->id;
        $pattern = $prospectValidationSignal->pattern;

        $prospectValidationSignal->update(['active' => false]);
        $rules->clearCache();

        RevalidateProspectsForSignalJob::dispatch($signalId, $pattern);

        return redirect()
            ->route('settings.validation-rules.index')
            ->with('success', 'Validation signal deactivated. Re-validation queued.');
    }
}
