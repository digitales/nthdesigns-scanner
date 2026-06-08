<?php

namespace App\Http\Controllers;

use App\Models\SharedList;
use Illuminate\Http\RedirectResponse;

class SharedListController extends Controller
{
    public function destroy(SharedList $sharedList): RedirectResponse
    {
        $this->authorize('delete', $sharedList);

        $sharedList->update(['revoked_at' => now()]);

        return back()->with('success', 'Share link revoked.');
    }
}
