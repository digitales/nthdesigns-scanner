<?php

namespace Tests\Unit;

use App\Models\Prospect;
use App\Models\Search;
use App\Services\OutreachEmailGeneratorService;
use Tests\TestCase;

class OutreachEmailGeneratorServiceTest extends TestCase
{
    public function test_performance_instruction_when_score_below_30(): void
    {
        $search = new Search(['niche' => 'dental', 'city' => 'Leeds', 'country' => 'GB']);
        $prospect = new Prospect([
            'business_name' => 'Acme',
            'performance_score' => 25,
            'combined_score' => 60,
            'gbp_flags' => [],
            'a11y_flags' => [],
        ]);
        $prospect->setRelation('search', $search);

        $service = app(OutreachEmailGeneratorService::class);

        $line = $service->performancePromptInstruction($prospect);

        $this->assertNotNull($line);
        $this->assertStringContainsString('25/100', $line);
        $this->assertStringContainsString('secondary sentence', $line);
    }

    public function test_no_performance_instruction_when_score_high(): void
    {
        $search = new Search(['niche' => 'dental', 'city' => 'Leeds', 'country' => 'GB']);
        $prospect = new Prospect([
            'business_name' => 'Acme',
            'performance_score' => 80,
            'combined_score' => 60,
            'gbp_flags' => [],
            'a11y_flags' => [],
        ]);
        $prospect->setRelation('search', $search);

        $service = app(OutreachEmailGeneratorService::class);

        $this->assertNull($service->performancePromptInstruction($prospect));
    }
}
