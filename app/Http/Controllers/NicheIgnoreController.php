<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreNicheIgnoreRequest;
use App\Services\NicheExclusionService;
use Illuminate\Http\RedirectResponse;

class NicheIgnoreController extends Controller
{
    public function store(StoreNicheIgnoreRequest $request, NicheExclusionService $exclusions): RedirectResponse
    {
        $this->authorize('manageNicheExclusions');

        $niche = $request->validated('niche');

        $exclusions->ignoreManually($niche);

        return back()->with('success', "Niche \"{$niche}\" excluded from scans.");
    }

    public function destroy(StoreNicheIgnoreRequest $request, NicheExclusionService $exclusions): RedirectResponse
    {
        $this->authorize('manageNicheExclusions');

        $niche = $request->validated('niche');

        $exclusions->includeInScans($niche);

        return back()->with('success', "Niche \"{$niche}\" included in scans again.");
    }
}
