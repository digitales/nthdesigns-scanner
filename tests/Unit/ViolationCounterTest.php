<?php

namespace Tests\Unit;

use App\Support\ViolationCounter;
use PHPUnit\Framework\TestCase;

class ViolationCounterTest extends TestCase
{
    public function test_counts_violations_by_impact(): void
    {
        $counts = ViolationCounter::countByImpact([
            ['impact' => 'critical'],
            ['impact' => 'serious'],
            ['impact' => 'serious'],
            ['impact' => 'unknown'],
        ]);

        $this->assertSame([
            'critical' => 1,
            'serious' => 2,
            'moderate' => 0,
            'minor' => 0,
        ], $counts);
    }

    public function test_summarize_payload_includes_total(): void
    {
        $summary = ViolationCounter::summarizePayload([
            'violations' => [
                ['impact' => 'moderate'],
                ['impact' => 'minor'],
            ],
        ]);

        $this->assertSame([
            'critical' => 0,
            'serious' => 0,
            'moderate' => 1,
            'minor' => 1,
            'total' => 2,
        ], $summary);
    }
}
