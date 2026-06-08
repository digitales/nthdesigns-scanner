<?php

namespace App\Http\Controllers;

use App\Models\SharedList;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class PublicSharedListController extends Controller
{
    public function show(Request $request, string $token): Response|HttpResponse
    {
        $shared = SharedList::query()
            ->where('token', $token)
            ->with('prospectList')
            ->first();

        if (! $shared || ! $shared->isAccessible()) {
            abort(404);
        }

        $snapshot = $shared->snapshot ?? [];

        return Inertia::render('SharedList/Show', [
            'listName' => $snapshot['list_name'] ?? $shared->prospectList->name,
            'sharedAt' => $snapshot['shared_at'] ?? $shared->created_at->toISOString(),
            'rows' => $snapshot['rows'] ?? [],
        ]);
    }
}
