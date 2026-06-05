<?php

namespace App\Http\Controllers;

use App\Http\Requests\FilterProspectListRequest;
use App\Http\Resources\ProspectListResource;
use App\Queries\ProspectListQuery;
use Inertia\Inertia;
use Inertia\Response;

class SavedProspectController extends Controller
{
    public function index(FilterProspectListRequest $request): Response
    {
        $filters = $request->validated();

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
