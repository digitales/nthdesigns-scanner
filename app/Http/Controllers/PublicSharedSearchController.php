<?php

namespace App\Http\Controllers;

use App\Models\SharedSearch;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class PublicSharedSearchController extends Controller
{
    public function show(Request $request, string $token): Response|HttpResponse
    {
        $shared = SharedSearch::query()
            ->where('token', $token)
            ->first();

        if (! $shared || ! $shared->isAccessible()) {
            abort(404);
        }

        $snapshot = $shared->snapshot ?? [];

        return Inertia::render('SharedSearch/Show', [
            'search' => $snapshot['search'] ?? [],
            'prospects' => $snapshot['prospects'] ?? [],
            'sharedAt' => $snapshot['search']['shared_at'] ?? $shared->created_at->toISOString(),
        ]);
    }
}
