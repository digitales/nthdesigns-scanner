<?php

namespace App\Http\Controllers;

use App\Http\Requests\SyncProspectTagsRequest;
use App\Models\Prospect;
use App\Services\TagService;
use Illuminate\Http\RedirectResponse;

class ProspectTagController extends Controller
{
    public function sync(SyncProspectTagsRequest $request, Prospect $prospect, TagService $tags): RedirectResponse
    {
        $this->authorize('update', $prospect);

        $data = $request->validated();
        $tag = $tags->findOrCreate($request->user(), $data['tag_name']);

        if ($data['action'] === 'attach') {
            $prospect->tags()->syncWithoutDetaching([$tag->id]);
        } else {
            $prospect->tags()->detach($tag->id);
        }

        return back();
    }
}
