<?php

namespace App\Http\Controllers;

use App\Enums\IgnoredProspectReason;
use App\Http\Requests\FilterIgnoredProspectsRequest;
use App\Models\IgnoredProspect;
use App\Services\ProspectExclusionService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class IgnoredProspectController extends Controller
{
    public function index(FilterIgnoredProspectsRequest $request, ProspectExclusionService $exclusions): Response
    {
        $filters = $request->validated();
        $reason = $filters['reason'] ?? null;

        $paginator = $exclusions->paginateForUser($request->user(), $reason);

        return Inertia::render('Ignored/Index', [
            'entries' => collect($paginator->items())->values(),
            'filters' => ['reason' => $reason],
            'pagination' => [
                'total' => $paginator->total(),
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
            ],
            'reasonOptions' => [
                ['value' => '', 'label' => 'All reasons'],
                ...collect(IgnoredProspectReason::cases())
                    ->map(fn (IgnoredProspectReason $reason) => ['value' => $reason->value, 'label' => $reason->label()])
                    ->all(),
            ],
        ]);
    }

    public function destroy(IgnoredProspect $ignoredProspect): RedirectResponse
    {
        $this->authorize('delete', $ignoredProspect);

        $ignoredProspect->delete();

        return back()->with('success', 'Prospect included in scans again.');
    }
}
