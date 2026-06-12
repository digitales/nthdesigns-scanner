<?php

namespace App\Http\Controllers;

use App\Http\Requests\LookupMarketCpcRequest;
use App\Services\MarketCpcLookupService;
use Illuminate\Http\RedirectResponse;

class MarketCpcController extends Controller
{
    public function __construct(
        private MarketCpcLookupService $lookup,
    ) {}

    /**
     * Load a previously saved market CPC from the database only (no external API calls).
     */
    public function load(LookupMarketCpcRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $default = $this->lookup->savedDefault(
            $request->user(),
            $validated['niche'],
            $validated['city'],
            $validated['country'],
        );

        if ($default === null || $default->cpc_benchmark === null) {
            return back()->with('error', 'No saved CPC default for this niche and city yet.');
        }

        return back()->with('market_cpc', $this->lookup->formatForForm($default));
    }

    /**
     * Fetch CPC from Google Ads only — does not run a niche search or Places discovery.
     */
    public function fetch(LookupMarketCpcRequest $request): RedirectResponse
    {
        if (! $this->lookup->isAvailable()) {
            return back()->with('error', 'Google Ads CPC lookup is not configured.');
        }

        $validated = $request->validated();

        $default = $this->lookup->fetchFromGoogleAds(
            $request->user(),
            $validated['niche'],
            $validated['city'],
            $validated['country'],
        );

        if ($default === null) {
            return back()->with('error', 'Google Ads returned no CPC data for this market. Try manual entry or different keywords.');
        }

        return back()->with([
            'success' => sprintf(
                'CPC £%s saved for %s in %s (Google Ads only — no search run).',
                number_format((float) $default->cpc_benchmark, 2),
                $validated['niche'],
                $validated['city'],
            ),
            'market_cpc' => $this->lookup->formatForForm($default),
        ]);
    }
}
