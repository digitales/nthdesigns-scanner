<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreNicheNoteRequest;
use App\Http\Requests\SyncNicheTagsRequest;
use App\Models\NicheNote;
use App\Models\NicheTagAssignment;
use App\Models\Search;
use App\Services\TagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NicheAnnotationController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $nicheLabel = $request->string('niche_label')->toString();
        $city = $request->filled('city') ? $request->string('city')->toString() : null;

        abort_if($nicheLabel === '', 422, 'niche_label is required.');

        $userId = $request->user()->id;

        $globalNotes = NicheNote::query()
            ->where('user_id', $userId)
            ->where('niche_label', $nicheLabel)
            ->whereNull('city')
            ->latest()
            ->get();

        $marketNotes = $city !== null
            ? NicheNote::query()
                ->where('user_id', $userId)
                ->where('niche_label', $nicheLabel)
                ->where('city', $city)
                ->latest()
                ->get()
            : collect();

        $globalTags = NicheTagAssignment::query()
            ->where('user_id', $userId)
            ->where('niche_label', $nicheLabel)
            ->whereNull('city')
            ->with('tag')
            ->get()
            ->map(fn ($a) => ['id' => $a->tag_id, 'name' => $a->tag->name, 'color' => $a->tag->color]);

        $marketTags = $city !== null
            ? NicheTagAssignment::query()
                ->where('user_id', $userId)
                ->where('niche_label', $nicheLabel)
                ->where('city', $city)
                ->with('tag')
                ->get()
                ->map(fn ($a) => ['id' => $a->tag_id, 'name' => $a->tag->name, 'color' => $a->tag->color])
            : collect();

        $relatedSearchCount = $city !== null
            ? Search::query()
                ->where('user_id', $userId)
                ->where('niche', $nicheLabel)
                ->where('city', $city)
                ->count()
            : 0;

        return response()->json([
            'global' => [
                'notes' => $globalNotes->map(fn ($n) => [
                    'id' => $n->id,
                    'body' => $n->body,
                    'created_at' => $n->created_at->diffForHumans(),
                ]),
                'tags' => $globalTags,
            ],
            'market' => [
                'notes' => $marketNotes->map(fn ($n) => [
                    'id' => $n->id,
                    'body' => $n->body,
                    'created_at' => $n->created_at->diffForHumans(),
                ]),
                'tags' => $marketTags,
                'related_search_count' => $relatedSearchCount,
            ],
            'tag_suggestions' => app(TagService::class)->suggestionsFor($request->user()),
        ]);
    }

    public function storeNote(StoreNicheNoteRequest $request): RedirectResponse
    {
        $data = $request->validated();

        NicheNote::create([
            'user_id' => $request->user()->id,
            'niche_label' => $data['niche_label'],
            'city' => $data['city'] ?? null,
            'body' => $data['body'],
        ]);

        return back()->with('success', 'Note added.');
    }

    public function syncTags(SyncNicheTagsRequest $request, TagService $tags): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();
        $tag = $tags->findOrCreate($user, $data['tag_name']);

        $query = NicheTagAssignment::query()
            ->where('user_id', $user->id)
            ->where('niche_label', $data['niche_label'])
            ->where('tag_id', $tag->id);

        if (! empty($data['city'])) {
            $query->where('city', $data['city']);
        } else {
            $query->whereNull('city');
        }

        if ($data['action'] === 'attach') {
            NicheTagAssignment::firstOrCreate([
                'user_id' => $user->id,
                'tag_id' => $tag->id,
                'niche_label' => $data['niche_label'],
                'city' => $data['city'] ?? null,
            ]);
        } else {
            $query->delete();
        }

        return back();
    }
}
