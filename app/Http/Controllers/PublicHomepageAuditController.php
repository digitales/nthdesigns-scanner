<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHomepageAuditRequest;
use App\Services\HomepageAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class PublicHomepageAuditController extends Controller
{
    public function store(StoreHomepageAuditRequest $request, HomepageAuditService $audits): JsonResponse
    {
        if (! $audits->isEnabled()) {
            return response()->json([
                'message' => 'Homepage audits are not available right now.',
            ], 503);
        }

        try {
            $result = $audits->start(
                $request->validated('website_url'),
                $request->ip() ?? '',
            );
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json($result, 201);
    }

    public function show(string $token, HomepageAuditService $audits): JsonResponse
    {
        $status = $audits->status($token);

        if ($status === null) {
            return response()->json([
                'message' => 'Audit not found.',
            ], 404);
        }

        return response()->json($status);
    }
}
