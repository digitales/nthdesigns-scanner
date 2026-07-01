<?php

namespace App\Http\Controllers;

use App\Http\Requests\FilterOutreachPipelineRequest;
use App\Http\Resources\OutreachSelectionResource;
use App\Models\OutreachSelection;
use App\Services\Outreach\OutreachQueueLoader;
use Inertia\Inertia;
use Inertia\Response;

class OutreachPipelineController extends Controller
{
    public function __construct(
        private OutreachQueueLoader $queue,
    ) {}

    public function index(FilterOutreachPipelineRequest $request): Response
    {
        $filters = $request->validated();
        $paginator = $this->queue->pipelinePaginator($request->user(), $filters);

        return Inertia::render('Lists/Pipeline', [
            'rows' => $paginator->getCollection()
                ->map(fn (OutreachSelection $selection) => OutreachSelectionResource::format($selection, $this->queue))
                ->values(),
            'filters' => $filters,
            'meta' => ['total' => $paginator->total()],
            'pagination' => [
                'total' => $paginator->total(),
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }
}
