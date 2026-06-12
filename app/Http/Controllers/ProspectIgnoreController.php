<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreIgnoredProspectRequest;
use App\Models\Prospect;
use App\Services\ProspectExclusionService;
use App\Services\ProspectUnsubscribeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProspectIgnoreController extends Controller
{
    public function store(
        StoreIgnoredProspectRequest $request,
        Prospect $prospect,
        ProspectExclusionService $exclusions,
    ): RedirectResponse {
        $exclusions->ignore(
            $request->user(),
            $prospect,
            $request->validated('reason'),
            $request->validated('note'),
        );

        return back()->with('success', 'Prospect ignored and will be skipped in future scans.');
    }

    public function destroy(
        Request $request,
        Prospect $prospect,
        ProspectExclusionService $exclusions,
        ProspectUnsubscribeService $unsubscribe,
    ): RedirectResponse {
        $this->authorize('view', $prospect);

        $ignored = $exclusions->findForUser($request->user()->id, $prospect->place_id);

        if ($ignored !== null) {
            $unsubscribe->liftSuppressionForIgnored($request->user(), $ignored);
        }

        $exclusions->includeInScans($request->user(), $prospect);

        return back()->with('success', 'Prospect included in scans again.');
    }
}
