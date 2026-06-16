<?php

namespace App\Http\Controllers;

use App\Models\SharedSearch;
use Illuminate\Http\RedirectResponse;

class SharedSearchController extends Controller
{
    public function destroy(SharedSearch $sharedSearch): RedirectResponse
    {
        $this->authorize('delete', $sharedSearch);

        $sharedSearch->update(['revoked_at' => now()]);

        return back()->with('success', 'Share link revoked.');
    }
}
