<?php

namespace Tests\Unit;

use App\Enums\ProspectFinancialStatus;
use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use App\Services\CompaniesHouseLookupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CompaniesHouseLookupServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_sets_caution_when_api_key_missing(): void
    {
        config(['services.companies_house.key' => null]);

        $prospect = $this->makeProspect();

        app(CompaniesHouseLookupService::class)->check($prospect);

        $prospect->refresh();

        $this->assertSame(ProspectFinancialStatus::Caution, $prospect->companies_house_status);
        $this->assertSame(['Companies House API key not configured'], $prospect->companies_house_flags);
        $this->assertNotNull($prospect->companies_house_checked_at);
    }

    public function test_check_sets_no_match_when_search_returns_nothing_confident(): void
    {
        config(['services.companies_house.key' => 'test-key']);

        $prospect = $this->makeProspect(['business_name' => 'Acorn Dental Practice']);

        Http::fake([
            '*/search/companies*' => Http::response(['items' => []]),
        ]);

        app(CompaniesHouseLookupService::class)->check($prospect);

        $prospect->refresh();

        $this->assertSame(ProspectFinancialStatus::NoMatch, $prospect->companies_house_status);
        $this->assertNull($prospect->companies_house_number);
    }

    public function test_check_matches_active_company_with_no_red_flags(): void
    {
        config(['services.companies_house.key' => 'test-key']);

        $prospect = $this->makeProspect([
            'business_name' => 'Acorn Dental Practice',
            'address' => '1 High Street, Bristol, BS1 4ST',
        ]);

        Http::fake([
            '*/search/companies*' => Http::response(['items' => [
                [
                    'company_number' => '12345678',
                    'title' => 'ACORN DENTAL PRACTICE LTD',
                    'address_snippet' => '1 High Street, Bristol, BS1 4ST',
                ],
            ]]),
            '*/company/12345678/charges*' => Http::response(['total_count' => 0]),
            '*/company/12345678' => Http::response([
                'company_status' => 'active',
                'date_of_creation' => now()->subYears(5)->toDateString(),
                'accounts' => ['overdue' => false],
            ]),
        ]);

        app(CompaniesHouseLookupService::class)->check($prospect);

        $prospect->refresh();

        $this->assertSame(ProspectFinancialStatus::Matched, $prospect->companies_house_status);
        $this->assertSame('12345678', $prospect->companies_house_number);
        $this->assertNotNull($prospect->companies_house_checked_at);
    }

    public function test_check_flags_dissolved_company(): void
    {
        config(['services.companies_house.key' => 'test-key']);

        $prospect = $this->makeProspect([
            'business_name' => 'Acorn Dental Practice',
            'address' => '1 High Street, Bristol, BS1 4ST',
        ]);

        Http::fake([
            '*/search/companies*' => Http::response(['items' => [
                [
                    'company_number' => '12345678',
                    'title' => 'ACORN DENTAL PRACTICE LTD',
                    'address_snippet' => '1 High Street, Bristol, BS1 4ST',
                ],
            ]]),
            '*/company/12345678/charges*' => Http::response(['total_count' => 0]),
            '*/company/12345678' => Http::response([
                'company_status' => 'dissolved',
                'date_of_creation' => now()->subYears(5)->toDateString(),
            ]),
        ]);

        app(CompaniesHouseLookupService::class)->check($prospect);

        $prospect->refresh();

        $this->assertSame(ProspectFinancialStatus::Dissolved, $prospect->companies_house_status);
        $this->assertStringContainsString('exclude from outreach', $prospect->companies_house_flags[0]);
    }

    public function test_check_flags_overdue_accounts_as_caution(): void
    {
        config(['services.companies_house.key' => 'test-key']);

        $prospect = $this->makeProspect([
            'business_name' => 'Acorn Dental Practice',
            'address' => '1 High Street, Bristol, BS1 4ST',
        ]);

        Http::fake([
            '*/search/companies*' => Http::response(['items' => [
                [
                    'company_number' => '12345678',
                    'title' => 'ACORN DENTAL PRACTICE LTD',
                    'address_snippet' => '1 High Street, Bristol, BS1 4ST',
                ],
            ]]),
            '*/company/12345678/charges*' => Http::response(['total_count' => 1]),
            '*/company/12345678' => Http::response([
                'company_status' => 'active',
                'date_of_creation' => now()->subYears(5)->toDateString(),
                'accounts' => ['overdue' => true],
            ]),
        ]);

        app(CompaniesHouseLookupService::class)->check($prospect);

        $prospect->refresh();

        $this->assertSame(ProspectFinancialStatus::Caution, $prospect->companies_house_status);
        $flags = implode(' ', $prospect->companies_house_flags);
        $this->assertStringContainsString('Accounts overdue', $flags);
        $this->assertStringContainsString('1 charge(s) registered', $flags);
    }

    public function test_check_uses_registered_company_number_directly(): void
    {
        config(['services.companies_house.key' => 'test-key']);

        $prospect = $this->makeProspect([
            'business_name' => 'Smile Dental Manchester',
            'registered_company_number' => '87654321',
        ]);

        Http::fake([
            '*/search/companies*' => function () {
                throw new \RuntimeException('Search should not be called when number is registered');
            },
            '*/company/87654321/charges*' => Http::response(['total_count' => 0]),
            '*/company/87654321' => Http::response([
                'company_status' => 'active',
                'date_of_creation' => now()->subYears(3)->toDateString(),
                'accounts' => ['overdue' => false],
            ]),
        ]);

        app(CompaniesHouseLookupService::class)->check($prospect);

        $prospect->refresh();

        $this->assertSame('87654321', $prospect->companies_house_number);
        $this->assertSame(ProspectFinancialStatus::Matched, $prospect->companies_house_status);
    }

    public function test_check_uses_registered_company_name_instead_of_business_name(): void
    {
        config(['services.companies_house.key' => 'test-key']);

        $prospect = $this->makeProspect([
            'business_name' => 'Smile Dental Manchester',
            'registered_company_name' => 'North West Dental Holdings Ltd',
            'address' => '1 High Street, Bristol, BS1 4ST',
        ]);

        Http::fake([
            '*/search/companies*' => function ($request) {
                $query = $request->data()['q'] ?? '';
                if ($query !== 'North West Dental Holdings Ltd') {
                    return Http::response(['items' => []]);
                }

                return Http::response(['items' => [
                    [
                        'company_number' => '11223344',
                        'title' => 'NORTH WEST DENTAL HOLDINGS LTD',
                        'address_snippet' => '1 High Street, Bristol, BS1 4ST',
                    ],
                ]]);
            },
            '*/company/11223344/charges*' => Http::response(['total_count' => 0]),
            '*/company/11223344' => Http::response([
                'company_status' => 'active',
                'date_of_creation' => now()->subYears(4)->toDateString(),
                'accounts' => ['overdue' => false],
            ]),
        ]);

        app(CompaniesHouseLookupService::class)->check($prospect);

        $prospect->refresh();

        $this->assertSame('11223344', $prospect->companies_house_number);
        $this->assertStringContainsString('Matched via registered company name', $prospect->companies_house_summary);
    }

    public function test_check_sets_caution_when_registered_number_not_found(): void
    {
        config(['services.companies_house.key' => 'test-key']);

        $prospect = $this->makeProspect([
            'business_name' => 'Smile Dental Manchester',
            'registered_company_number' => '99999999',
        ]);

        Http::fake([
            '*/company/99999999' => Http::response([], 404),
        ]);

        app(CompaniesHouseLookupService::class)->check($prospect);

        $prospect->refresh();

        $this->assertSame(ProspectFinancialStatus::Caution, $prospect->companies_house_status);
        $this->assertContains('Registered company number not found on Companies House', $prospect->companies_house_flags);
    }

    public function test_check_falls_back_to_business_name_when_no_registration(): void
    {
        config(['services.companies_house.key' => 'test-key']);

        $prospect = $this->makeProspect([
            'business_name' => 'Acorn Dental Practice',
            'address' => '1 High Street, Bristol, BS1 4ST',
        ]);

        Http::fake([
            '*/search/companies*' => Http::response(['items' => [
                [
                    'company_number' => '12345678',
                    'title' => 'ACORN DENTAL PRACTICE LTD',
                    'address_snippet' => '1 High Street, Bristol, BS1 4ST',
                ],
            ]]),
            '*/company/12345678/charges*' => Http::response(['total_count' => 0]),
            '*/company/12345678' => Http::response([
                'company_status' => 'active',
                'date_of_creation' => now()->subYears(5)->toDateString(),
                'accounts' => ['overdue' => false],
            ]),
        ]);

        app(CompaniesHouseLookupService::class)->check($prospect);

        $prospect->refresh();

        $this->assertSame('12345678', $prospect->companies_house_number);
        $this->assertStringNotContainsString('Matched via registered company name', (string) $prospect->companies_house_summary);
    }

    private function makeProspect(array $attributes = []): Prospect
    {
        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id]);

        return Prospect::factory()->create(array_merge([
            'search_id' => $search->id,
        ], $attributes));
    }
}
