<?php

namespace Tests\Unit;

use App\Services\CompaniesHouseTalkingPointsGenerator;
use Tests\TestCase;

class CompaniesHouseTalkingPointsGeneratorTest extends TestCase
{
    public function test_generate_includes_overdue_accounts_and_financials(): void
    {
        $generator = new CompaniesHouseTalkingPointsGenerator;

        $points = $generator->generate(
            [
                'recent_activity' => [],
                'financials' => [
                    'status' => 'available',
                    'turnover' => 450_000,
                    'profit_before_tax' => 62_000,
                ],
                'company_snapshot' => [
                    'incorporated_on' => now()->subYears(5)->toDateString(),
                ],
            ],
            ['Accounts overdue at Companies House — possible cash flow or compliance issue'],
        );

        $this->assertContains(
            'Accounts overdue at Companies House — possible cash flow or compliance pressure',
            $points,
        );
        $this->assertTrue(
            collect($points)->contains(fn (string $point) => str_contains($point, 'turnover')),
        );
    }

    public function test_generate_includes_recent_officer_appointment(): void
    {
        $generator = new CompaniesHouseTalkingPointsGenerator;

        $points = $generator->generate(
            [
                'recent_activity' => [[
                    'date' => now()->subMonths(2)->toDateString(),
                    'category' => 'officers',
                    'description' => 'Appointment of director — Jane Smith',
                ]],
                'financials' => ['status' => 'unavailable'],
                'company_snapshot' => [],
            ],
            [],
        );

        $this->assertContains(
            'Director appointed within the last year — possible leadership change',
            $points,
        );
    }
}
