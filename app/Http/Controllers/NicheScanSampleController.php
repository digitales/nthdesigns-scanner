<?php

namespace App\Http\Controllers;

use App\Jobs\ScanNicheJob;
use App\Models\NicheScan;
use App\Support\ScrapingQueue;
use Illuminate\Http\JsonResponse;

class NicheScanSampleController extends Controller
{
    public function show(NicheScan $nicheScan): JsonResponse
    {
        if ($nicheScan->status === 'failed') {
            return response()->json([
                'status' => 'failed',
                'message' => 'Sample scan failed.',
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

        if ($nicheScan->status !== 'pending') {
            ScrapingQueue::dispatch(new ScanNicheJob(
                niche: $nicheScan->niche,
                nicheQuery: $nicheScan->niche_query,
                city: $nicheScan->city,
                country: $nicheScan->country,
                sample: (int) config('niches.sample_size', 5),
                scanDate: now('Europe/London')->toDateString(),
            ));
        }

        return response()->json(['status' => 'loading'], 202);
    }
}
