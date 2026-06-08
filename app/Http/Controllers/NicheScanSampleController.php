<?php

namespace App\Http\Controllers;

use App\Enums\NicheScanStatus;
use App\Jobs\ScanNicheJob;
use App\Models\NicheScan;
use Illuminate\Http\JsonResponse;

class NicheScanSampleController extends Controller
{
    public function show(NicheScan $nicheScan): JsonResponse
    {
        if ($nicheScan->status === NicheScanStatus::Failed) {
            return response()->json([
                'status' => 'failed',
                'message' => $nicheScan->error_message ?? 'Sample scan failed.',
            ], 422);
        }

        if ($nicheScan->sample_preview !== null) {
            return response()->json([
                'status' => 'ready',
                'niche' => $nicheScan->niche,
                'city' => $nicheScan->city,
                'country' => $nicheScan->country,
                'niche_query' => $nicheScan->niche_query,
                'sampled_count' => $nicheScan->sampled_count,
                'result_count' => $nicheScan->result_count,
                'opportunity_score' => $nicheScan->opportunity_score,
                'ran_at_human' => $nicheScan->ran_at?->diffForHumans() ?? '—',
                'items' => $nicheScan->sample_preview,
            ]);
        }

        if ($nicheScan->status === NicheScanStatus::Pending) {
            return response()->json(['status' => 'loading'], 202);
        }

        $claimed = NicheScan::query()
            ->whereKey($nicheScan->id)
            ->whereNull('sample_preview')
            ->where('status', NicheScanStatus::Complete)
            ->update(['status' => NicheScanStatus::Pending]);

        if ($claimed) {
            ScanNicheJob::dispatch(
                niche: $nicheScan->niche,
                nicheQuery: $nicheScan->niche_query,
                city: $nicheScan->city,
                country: $nicheScan->country,
                sample: max(1, (int) ($nicheScan->sampled_count ?: config('niches.sample_size', 5))),
                scanDate: $nicheScan->scan_date->toDateString(),
            );
        }

        return response()->json(['status' => 'loading'], 202);
    }
}
