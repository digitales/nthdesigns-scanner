<?php

namespace App\Http\Controllers;

use App\Jobs\CheckCompaniesHouseJob;
use App\Models\Prospect;
use Illuminate\Http\JsonResponse;

class CompaniesHouseController extends Controller
{
    public function check(Prospect $prospect): JsonResponse
    {
        $this->authorize('view', $prospect);

        CheckCompaniesHouseJob::dispatch($prospect);

        return response()->json(['message' => 'Companies House check queued.'], 202);
    }
}
