<?php

namespace Tests\Unit;

use App\Jobs\ScanNicheJob;
use Tests\TestCase;

class ScanNicheJobOpportunityScoreTest extends TestCase
{
    private const AVG = 70.0;

    private const PCT_NO_WEBSITE = 100.0;

    private const PCT_LOW_REVIEWS = 100.0;

    private const RAW = 88.0;

    public function test_returns_zero_when_result_count_is_zero(): void
    {
        $this->assertSame(0.0, ScanNicheJob::opportunityScore(
            self::AVG,
            self::PCT_NO_WEBSITE,
            self::PCT_LOW_REVIEWS,
            0,
        ));
    }

    public function test_returns_zero_when_result_count_is_one(): void
    {
        $this->assertSame(0.0, ScanNicheJob::opportunityScore(
            self::AVG,
            self::PCT_NO_WEBSITE,
            self::PCT_LOW_REVIEWS,
            1,
        ));
    }

    public function test_returns_half_raw_when_result_count_is_two(): void
    {
        $this->assertSame(44.0, ScanNicheJob::opportunityScore(
            self::AVG,
            self::PCT_NO_WEBSITE,
            self::PCT_LOW_REVIEWS,
            2,
        ));
    }

    public function test_returns_full_raw_when_result_count_is_three_or_more(): void
    {
        $this->assertSame(self::RAW, ScanNicheJob::opportunityScore(
            self::AVG,
            self::PCT_NO_WEBSITE,
            self::PCT_LOW_REVIEWS,
            3,
        ));

        $this->assertSame(self::RAW, ScanNicheJob::opportunityScore(
            self::AVG,
            self::PCT_NO_WEBSITE,
            self::PCT_LOW_REVIEWS,
            54,
        ));
    }
}
