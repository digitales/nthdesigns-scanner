<?php

namespace App\Http\Controllers;

use App\Models\IgnoredProspect;
use App\Services\ProspectExclusionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class IgnoredProspectController extends Controller
{
    public function index(Request $request, ProspectExclusionService $exclusions): Response
    {
        $filters = $request->validate([
            'reason' => [
                'nullable',
                'string',
                Rule::in([
                    IgnoredProspect::REASON_ACQUIRED,
                    IgnoredProspect::REASON_COLD,
                    IgnoredProspect::REASON_OUTREACH_FAILED,
                    IgnoredProspect::REASON_OTHER,
                ]),
            ],
        ]);

        $entries = $exclusions->listForUser($request->user(), $filters['reason'] ?? null);

        return Inertia::render('Ignored/Index', [
            'entries' => $entries->values(),
            'filters' => $filters,
            'meta'    => ['total' => $entries->count()],
            'reasonOptions' => [
                ['value' => '', 'label' => 'All reasons'],
                ['value' => IgnoredProspect::REASON_ACQUIRED, 'label' => 'Company acquired'],
                ['value' => IgnoredProspect::REASON_COLD, 'label' => 'Cold lead'],
                ['value' => IgnoredProspect::REASON_OUTREACH_FAILED, 'label' => 'Outreach did not work'],
                ['value' => IgnoredProspect::REASON_OTHER, 'label' => 'Other'],
            ],
        ]);
    }

    public function destroy(Request $request, IgnoredProspect $ignoredProspect): RedirectResponse
    {
        $this->authorize('delete', $ignoredProspect);

        $ignoredProspect->delete();

        return back()->with('success', 'Prospect included in scans again.');
    }
}
