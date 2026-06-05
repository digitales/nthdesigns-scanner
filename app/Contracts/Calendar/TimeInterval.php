<?php

namespace App\Contracts\Calendar;

use Carbon\CarbonInterface;

readonly class TimeInterval
{
    public function __construct(
        public CarbonInterface $start,
        public CarbonInterface $end,
    ) {}

    public function overlaps(self $other): bool
    {
        return $this->start < $other->end && $other->start < $this->end;
    }
}
