<?php

namespace App\Http\Controllers;

use App\Models\Prospect;
use App\Services\ProspectUnsubscribeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProspectUnsubscribeController extends Controller
{
    public function store(
        Request $request,
        Prospect $prospect,
        ProspectUnsubscribeService $unsubscribe,
    ): RedirectResponse {
        $this->authorize('update', $prospect);

        $unsubscribe->unsubscribe(
            $request->user(),
            $prospect,
            \App\Enums\SuppressionSource::Operator,
        );

        return back()->with('success', "Email unsubscribed. This contact won't receive outreach.");
    }
}
