<?php

namespace App\Services\KeywordPlanner;

readonly class KeywordPlannerImportResult
{
    /**
     * @param  list<string>  $keywords
     */
    public function __construct(
        public float $benchmark,
        public array $keywords,
        public int $commercialCount,
        public int $totalCount,
    ) {}
}
