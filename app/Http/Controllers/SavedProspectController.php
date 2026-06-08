<?php

namespace App\Http\Controllers;

use App\Http\Requests\FilterProspectListRequest;
use Illuminate\Http\RedirectResponse;

class SavedProspectController extends Controller
{
    public function index(FilterProspectListRequest $request): RedirectResponse
    {
        return redirect()->route('lists.browse', $request->query());
    }
}
