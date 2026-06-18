<?php

namespace App\Http\Controllers;

use App\Enums\ProspectValidatorStatus;
use App\Http\Requests\StoreProspectValidatorOverrideRequest;
use App\Jobs\ValidateProspectJob;
use App\Models\Prospect;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class ProspectValidatorController extends Controller
{
    public function validateProspect(Prospect $prospect): JsonResponse
    {
        $this->authorize('view', $prospect);

        ValidateProspectJob::dispatch($prospect);

        return response()->json(['message' => 'Validation queued.'], 202);
    }

    public function storeOverride(
        StoreProspectValidatorOverrideRequest $request,
        Prospect $prospect,
    ): RedirectResponse {
        $this->authorize('view', $prospect);

        $validated = $request->validated();

        $status = $validated['status'];

        $prospect->update([
            'validator_override_status' => $status instanceof ProspectValidatorStatus ? $status->value : $status,
            'validator_override_note' => $validated['note'] ?? null,
            'validator_override_by' => $request->user()->id,
            'validator_override_at' => now(),
        ]);

        ValidateProspectJob::dispatch($prospect->fresh());

        return back()->with('success', 'Validation override saved.');
    }

    public function destroyOverride(Prospect $prospect): RedirectResponse
    {
        $this->authorize('view', $prospect);

        $prospect->update([
            'validator_override_status' => null,
            'validator_override_note' => null,
            'validator_override_by' => null,
            'validator_override_at' => null,
        ]);

        ValidateProspectJob::dispatch($prospect->fresh());

        return back()->with('success', 'Validation override cleared.');
    }
}
