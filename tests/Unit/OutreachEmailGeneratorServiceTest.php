<?php

namespace Tests\Unit;

use App\Models\Prospect;
use App\Models\ProspectReport;
use App\Models\Search;
use App\Services\AgencyBookingService;
use App\Services\OutreachEmailGeneratorService;
use ReflectionMethod;
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

    public function test_report_booking_instruction_when_native_booking_active(): void
    {
        $this->mock(AgencyBookingService::class, function ($mock): void {
            $mock->shouldReceive('nativeBookingActive')->andReturn(true);
        });

        $service = app(OutreachEmailGeneratorService::class);

        $instruction = $service->reportBookingInstruction();

        $this->assertStringContainsString('Next step', $instruction);
        $this->assertStringContainsString('inline booking', $instruction);
        $this->assertStringContainsString('Do not include any separate booking', $instruction);
    }

    public function test_report_booking_instruction_when_native_booking_inactive(): void
    {
        $this->mock(AgencyBookingService::class, function ($mock): void {
            $mock->shouldReceive('nativeBookingActive')->andReturn(false);
        });

        $service = app(OutreachEmailGeneratorService::class);

        $instruction = $service->reportBookingInstruction();

        $this->assertStringContainsString('Next step', $instruction);
        $this->assertStringNotContainsString('TidyCal', $instruction);
        $this->assertStringContainsString('Do not include any separate booking', $instruction);
    }

    public function test_user_prompt_steers_to_report_booking_not_tidycal(): void
    {
        config(['scanner.report_booking_url' => 'https://tidycal.com/handle']);

        $this->mock(AgencyBookingService::class, function ($mock): void {
            $mock->shouldReceive('nativeBookingActive')->andReturn(false);
        });

        $search = new Search(['niche' => 'dental', 'city' => 'Leeds', 'country' => 'GB']);
        $prospect = new Prospect([
            'business_name' => 'Acme Dental',
            'performance_score' => 80,
            'combined_score' => 60,
            'gbp_flags' => [],
            'a11y_flags' => [],
        ]);
        $prospect->setRelation('search', $search);

        $report = new ProspectReport(['token' => 'abc123']);
        $reportUrl = url('/r/'.$report->token);

        $service = app(OutreachEmailGeneratorService::class);
        $method = new ReflectionMethod(OutreachEmailGeneratorService::class, 'buildUserPrompt');
        $method->setAccessible(true);

        $prompt = $method->invoke($service, $prospect, 'gbp', $reportUrl, []);

        $this->assertStringContainsString($reportUrl, $prompt);
        $this->assertStringContainsString('Next step', $prompt);
        $this->assertStringNotContainsString('tidycal', strtolower($prompt));
        $this->assertStringNotContainsString('Booking link for a call', $prompt);
    }
}
