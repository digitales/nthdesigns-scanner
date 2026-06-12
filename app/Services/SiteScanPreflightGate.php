<?php

namespace App\Services;

use App\Jobs\CombineScoresJob;
use App\Models\Prospect;

class SiteScanPreflightGate
{
    public function __construct(
        private WebsiteReachabilityService $reachability,
        private SiteScanFailureRecorder $recorder,
        private SearchStatusService $searchStatus,
    ) {}

    public function passOrFail(Prospect $prospect): bool
    {
        $prospect = $prospect->fresh();

        if (! $prospect || empty($prospect->website_url)) {
            return true;
        }

        $result = $this->reachability->check($prospect->website_url);

        if ($result->isReachable()) {
            return true;
        }

        $this->recorder->recordPreflightFailure(
            $prospect,
            $result->failureMessage ?? 'Site unreachable',
        );

        CombineScoresJob::dispatch($prospect->fresh());
        $this->searchStatus->refresh($prospect->search);

        return false;
    }
}
