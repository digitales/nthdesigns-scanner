<?php

namespace App\Http\Controllers;

use App\Models\OutreachEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OutreachEmailController extends Controller
{
    public function markSent(Request $request, OutreachEmail $outreachEmail): RedirectResponse
    {
        $this->authorize('update', $outreachEmail);

        $outreachEmail->update(['sent_at' => now()]);

        return back();
    }

    public function markResponse(Request $request, OutreachEmail $outreachEmail): RedirectResponse
    {
        $this->authorize('update', $outreachEmail);

        $outreachEmail->update(['response_received' => true]);

        return back();
    }
}
