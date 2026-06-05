<?php

namespace App\Services;

use App\Support\GbpFieldExtractor;

class BenchmarkNormalizer
{
    public function __construct(private GbpFieldExtractor $fields) {}

    /**
     * @return array{
     *     place_id: string|null,
     *     name: string,
     *     review_count: int,
     *     photo_count: int,
     *     rating: float|null,
     *     has_description: bool,
     *     hours_complete: bool
     * }
     */
    public function fromPlace(array $place): array
    {
        return $this->fields->benchmarkFields($place);
    }
}
