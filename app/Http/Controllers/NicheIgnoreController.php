<?php

namespace App\Http\Controllers;

use App\Services\NicheExclusionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NicheIgnoreController extends Controller
{
    public function store(Request $request, NicheExclusionService $exclusions): RedirectResponse
    {
        $validated = $request->validate([
            'niche' => ['required', 'string', 'max:255'],
        ]);

        $exclusions->ignoreManually($validated['niche']);

        return back()->with('success', "Niche \"{$validated['niche']}\" excluded from scans.");
    }

    public function destroy(Request $request, NicheExclusionService $exclusions): RedirectResponse
    {
        $validated = $request->validate([
            'niche' => ['required', 'string', 'max:255'],
        ]);

        $exclusions->includeInScans($validated['niche']);

        return back()->with('success', "Niche \"{$validated['niche']}\" included in scans again.");
    }
}
