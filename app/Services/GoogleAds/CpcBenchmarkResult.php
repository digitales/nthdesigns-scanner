<?php

namespace App\Services\GoogleAds;

readonly class CpcBenchmarkResult
{
    /**
     * @param  list<string>  $keywords
     */
    public function __construct(
        public float $benchmark,
        public array $keywords,
        public ?string $geoTarget = null,
        public string $source = 'google_ads',
    ) {}
}
