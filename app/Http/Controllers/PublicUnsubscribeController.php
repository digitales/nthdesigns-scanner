<?php

namespace App\Http\Controllers;

use App\Enums\SuppressionSource;
use App\Models\Prospect;
use App\Services\ProspectUnsubscribeService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class PublicUnsubscribeController extends Controller
{
    public function show(
        Request $request,
        ProspectUnsubscribeService $unsubscribe,
    ): Response|HttpResponse {
        if (! $request->hasValidSignature()) {
            return Inertia::render('Public/Unsubscribe', [
                'success' => false,
                'message' => 'This link is invalid or has expired.',
            ])->toResponse($request)->setStatusCode(403);
        }

        $prospect = Prospect::with('search.user')->find($request->query('prospect'));

        if (! $prospect || ! $prospect->search?->user) {
            abort(404);
        }

        $requestedEmail = $unsubscribe->normalizeEmail($request->query('email'));
        $prospectEmail = $unsubscribe->normalizeEmail($prospect->email);

        if ($requestedEmail === null || $prospectEmail === null || $requestedEmail !== $prospectEmail) {
            return Inertia::render('Public/Unsubscribe', [
                'success' => false,
                'message' => 'This link is invalid or has expired.',
            ])->toResponse($request)->setStatusCode(403);
        }

        $unsubscribe->unsubscribe(
            $prospect->search->user,
            $prospect,
            SuppressionSource::SelfService,
        );

        return Inertia::render('Public/Unsubscribe', [
            'success' => true,
            'message' => "You've been unsubscribed. You won't receive further emails from us.",
        ]);
    }
}
