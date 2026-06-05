<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProspectListResource;
use App\Queries\ProspectListQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SavedProspectController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only([
            'from', 'to', 'niche', 'city', 'scan_type', 'min_score', 'dominant_angle', 'warm',
        ]);

        $listQuery = new ProspectListQuery($request->user());
        $prospects = $listQuery->apply($filters)->query()->get();

        $warmLeads = empty($filters['warm'])
            ? (new ProspectListQuery($request->user()))->warmLeads(10)
            : collect();

        return Inertia::render('Saved/Index', [
            'prospects' => $prospects->map(fn ($p) => ProspectListResource::format($p)),
            'warmLeads' => $warmLeads->map(fn ($p) => ProspectListResource::format($p)),
            'filters' => $filters,
            'meta' => ['total' => $prospects->count()],
        ]);
    }
}
