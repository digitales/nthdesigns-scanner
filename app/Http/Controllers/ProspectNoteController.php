<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProspectNoteRequest;
use App\Models\Prospect;
use Illuminate\Http\RedirectResponse;

class ProspectNoteController extends Controller
{
    public function store(StoreProspectNoteRequest $request, Prospect $prospect): RedirectResponse
    {
        $prospect->notes()->create([
            'user_id' => $request->user()->id,
            'body'    => $request->validated('body'),
        ]);

        return back()->with('success', 'Note added.');
    }
}
