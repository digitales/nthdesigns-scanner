<?php

namespace App\Http\Controllers;

use App\Jobs\CheckCompaniesHouseJob;
use App\Jobs\LoadCompaniesHouseDetailsJob;
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

    public function details(Prospect $prospect): JsonResponse
    {
        $this->authorize('view', $prospect);

        LoadCompaniesHouseDetailsJob::dispatch($prospect);

        return response()->json(['message' => 'Companies House details load queued.'], 202);
    }
}
