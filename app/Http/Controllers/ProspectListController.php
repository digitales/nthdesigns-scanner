<?php

namespace App\Http\Controllers;

use App\Enums\ListItemStatus;
use App\Enums\ProspectListType;
use App\Http\Requests\FilterProspectListRequest;
use App\Http\Requests\StoreProspectListItemsRequest;
use App\Http\Requests\StoreProspectListRequest;
use App\Http\Requests\UpdateProspectListItemRequest;
use App\Http\Requests\UpdateProspectListRequest;
use App\Http\Resources\ProspectListResource;
use App\Http\Resources\ProspectListSummaryResource;
use App\Models\Prospect;
use App\Models\ProspectList;
use App\Models\ProspectListItem;
use App\Models\SharedList;
use App\Queries\ProspectListQuery;
use App\Services\SharedListSnapshotBuilder;
use App\Services\TagService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProspectListController extends Controller
{
    public function index(Request $request): Response
    {
        $sort = $request->string('sort', 'updated')->toString();

        $lists = $request->user()
            ->prospectLists()
            ->latest('updated_at')
            ->get();

        return Inertia::render('Lists/Index', [
            'lists' => ProspectListSummaryResource::formatIndex($lists, $sort),
            'sort' => $sort,
            'tagSuggestions' => app(TagService::class)->suggestionsFor($request->user()),
        ]);
    }

    public function browse(FilterProspectListRequest $request): Response
    {
        $filters = $request->validated();

        $listQuery = new ProspectListQuery($request->user());
        $prospects = $listQuery->apply($filters)->query()->with('tags')->get();

        $warmLeads = empty($filters['warm'])
            ? (new ProspectListQuery($request->user()))->warmLeads(10)
            : collect();

        return Inertia::render('Lists/Browse', [
            'prospects' => $prospects->map(fn ($p) => ProspectListResource::format($p)),
            'warmLeads' => $warmLeads->map(fn ($p) => ProspectListResource::format($p)),
            'filters' => $filters,
            'meta' => ['total' => $prospects->count()],
            'tagSuggestions' => app(TagService::class)->suggestionsFor($request->user()),
            'manualLists' => $request->user()
                ->prospectLists()
                ->where('type', ProspectListType::Manual)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function store(StoreProspectListRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if ($data['type'] === ProspectListType::Smart->value && empty($data['filter'])) {
            $data['filter'] = [];
        }

        $list = $request->user()->prospectLists()->create([
            'name' => $data['name'],
            'type' => $data['type'],
            'description' => $data['description'] ?? null,
            'filter' => $data['type'] === ProspectListType::Smart->value ? ($data['filter'] ?? []) : null,
        ]);

        return redirect()->route('lists.show', $list)->with('success', 'List created.');
    }

    public function show(Request $request, ProspectList $list): Response
    {
        $this->authorize('view', $list);

        if ($list->isManual()) {
            $items = $list->items()
                ->with(['prospect.search', 'prospect.report', 'prospect.tags'])
                ->orderByDesc('created_at')
                ->get();

            $rows = $items->map(fn (ProspectListItem $item) => [
                'item_id' => $item->id,
                'prospect' => ProspectListResource::format($item->prospect),
                'status' => $item->status->value,
                'status_label' => $item->status->label(),
                'follow_up_at' => $item->follow_up_at?->format('Y-m-d'),
                'is_overdue' => $item->follow_up_at?->isPast() ?? false,
            ]);
        } else {
            $prospects = (new ProspectListQuery($request->user()))
                ->apply($list->filter ?? [])
                ->query()
                ->with(['search', 'report', 'tags'])
                ->get();

            $rows = $prospects->map(fn (Prospect $prospect) => [
                'item_id' => null,
                'prospect' => ProspectListResource::format($prospect),
                'status' => null,
                'status_label' => null,
                'follow_up_at' => null,
                'is_overdue' => false,
            ]);
        }

        return Inertia::render('Lists/Show', [
            'list' => ProspectListSummaryResource::format($list),
            'rows' => $rows,
            'statuses' => collect(ListItemStatus::cases())->map(fn ($s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ])->values(),
            'manualLists' => $request->user()
                ->prospectLists()
                ->where('type', ProspectListType::Manual)
                ->when($list->isManual(), fn ($q) => $q->whereKeyNot($list->id))
                ->orderBy('name')
                ->get(['id', 'name']),
            'tagSuggestions' => app(TagService::class)->suggestionsFor($request->user()),
        ]);
    }

    public function update(UpdateProspectListRequest $request, ProspectList $list): RedirectResponse
    {
        $this->authorize('update', $list);

        $data = $request->validated();

        if ($list->isSmart() && array_key_exists('filter', $data)) {
            $list->filter = $data['filter'];
        }

        $list->fill(collect($data)->only(['name', 'description'])->filter()->all());
        $list->save();

        return back()->with('success', 'List updated.');
    }

    public function destroy(ProspectList $list): RedirectResponse
    {
        $this->authorize('delete', $list);

        $list->delete();

        return redirect()->route('lists.index')->with('success', 'List deleted.');
    }

    public function storeItems(StoreProspectListItemsRequest $request, ProspectList $list): RedirectResponse
    {
        $this->authorize('update', $list);

        abort_unless($list->isManual(), 422, 'Only manual lists accept members.');

        $userId = $request->user()->id;
        $ids = collect($request->validated('prospect_ids'))
            ->unique()
            ->filter(fn (int $id) => Prospect::query()
                ->whereKey($id)
                ->whereHas('search', fn ($q) => $q->where('user_id', $userId))
                ->exists());

        foreach ($ids as $prospectId) {
            $list->items()->firstOrCreate(
                ['prospect_id' => $prospectId],
                ['status' => ListItemStatus::New],
            );
        }

        return back()->with('success', 'Prospects added to list.');
    }

    public function updateItem(
        UpdateProspectListItemRequest $request,
        ProspectList $list,
        ProspectListItem $item,
    ): RedirectResponse {
        $this->authorize('update', $list);
        abort_unless($item->prospect_list_id === $list->id, 404);

        $item->fill($request->validated());
        $item->save();

        return back();
    }

    public function destroyItem(ProspectList $list, ProspectListItem $item): RedirectResponse
    {
        $this->authorize('update', $list);
        abort_unless($item->prospect_list_id === $list->id, 404);

        $item->delete();

        return back()->with('success', 'Removed from list.');
    }

    public function share(Request $request, ProspectList $list, SharedListSnapshotBuilder $builder): RedirectResponse
    {
        $this->authorize('view', $list);

        $shared = SharedList::create([
            'user_id' => $request->user()->id,
            'prospect_list_id' => $list->id,
            'token' => (string) Str::uuid(),
            'snapshot' => $builder->build($list),
        ]);

        return back()->with([
            'success' => 'Share link created.',
            'shared_url' => url('/s/'.$shared->token),
        ]);
    }
}
