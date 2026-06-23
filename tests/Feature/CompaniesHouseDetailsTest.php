<?php

namespace Tests\Feature;

use App\Jobs\LoadCompaniesHouseDetailsJob;
use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use App\Services\CompaniesHouseDetailsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CompaniesHouseDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_details_endpoint_queues_job(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id' => Search::factory()->create(['user_id' => $user->id])->id,
            'companies_house_number' => '12345678',
        ]);

        $this->actingAs($user)
            ->postJson("/prospects/{$prospect->id}/companies-house/details")
            ->assertAccepted()
            ->assertJson(['message' => 'Companies House details load queued.']);

        Bus::assertDispatched(LoadCompaniesHouseDetailsJob::class);
    }

    public function test_load_persists_details_payload(): void
    {
        config(['services.companies_house.key' => 'test-key']);

        $prospect = Prospect::factory()->create([
            'search_id' => Search::factory()->create()->id,
            'companies_house_number' => '12345678',
            'companies_house_flags' => ['Active company, incorporated 5+ years ago'],
            'raw_companies_house_payload' => [
                'type' => 'ltd',
                'date_of_creation' => now()->subYears(5)->toDateString(),
                'registered_office_address' => [
                    'address_line_1' => '1 High Street',
                    'locality' => 'Bristol',
                    'postal_code' => 'BS1 4ST',
                ],
                'sic_codes' => ['86230'],
                'accounts' => [
                    'overdue' => false,
                    'last_accounts' => ['made_up_to' => '2025-03-31', 'type' => 'full'],
                    'next_accounts' => ['due_on' => '2026-09-30', 'overdue' => false],
                ],
            ],
        ]);

        $ixbrl = file_get_contents(base_path('tests/fixtures/companies-house/full-accounts.xhtml'));

        Http::fake([
            '*/company/12345678/filing-history*' => Http::response(['items' => [
                [
                    'date' => '2025-06-15',
                    'category' => 'accounts',
                    'description' => 'accounts-with-accounts-type-full',
                    'type' => 'AA',
                    'paper_filed' => false,
                    'transaction_id' => 'abc123',
                    'links' => ['document_metadata' => 'https://document-api.test/doc/1'],
                ],
                [
                    'date' => '2025-06-01',
                    'category' => 'officers',
                    'description' => 'appoint-person-director-company-with-name-jane-smith',
                    'type' => 'AP01',
                ],
            ]]),
            '*/company/12345678/officers*' => Http::response(['items' => [
                [
                    'name' => 'Jane Smith',
                    'officer_role' => 'director',
                    'appointed_on' => '2025-06-01',
                ],
            ]]),
            'https://document-api.test/doc/1' => Http::response([
                'links' => ['document' => 'https://document-api.test/doc/1/content'],
            ]),
            'https://document-api.test/doc/1/content' => Http::response($ixbrl, 200, [
                'Content-Type' => 'application/xhtml+xml',
            ]),
        ]);

        app(CompaniesHouseDetailsService::class)->load($prospect->fresh());

        $prospect->refresh();

        $this->assertNotNull($prospect->companies_house_details_loaded_at);
        $this->assertSame('ltd', $prospect->companies_house_details['company_snapshot']['company_type']);
        $this->assertCount(2, $prospect->companies_house_details['recent_activity']);
        $this->assertSame('Jane Smith', $prospect->companies_house_details['officers'][0]['name']);
        $this->assertSame('available', $prospect->companies_house_details['financials']['status']);
        $this->assertSame(450_000, $prospect->companies_house_details['financials']['turnover']);
        $this->assertNotEmpty($prospect->companies_house_details['talking_points']);
    }

    public function test_officers_fetch_falls_back_when_register_view_returns_server_error(): void
    {
        config(['services.companies_house.key' => 'test-key']);

        $prospect = Prospect::factory()->create([
            'search_id' => Search::factory()->create()->id,
            'companies_house_number' => '04968216',
            'raw_companies_house_payload' => ['type' => 'ltd'],
        ]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), 'register_view=true')) {
                return Http::response(['error' => 'Internal server error'], 500);
            }

            if (str_contains($request->url(), '/officers')) {
                return Http::response(['items' => [
                    [
                        'name' => 'Farida Khanam Ali',
                        'officer_role' => 'secretary',
                        'appointed_on' => '2005-02-15',
                    ],
                    [
                        'name' => 'Resigned Officer',
                        'officer_role' => 'director',
                        'appointed_on' => '2003-11-18',
                        'resigned_on' => '2003-11-21',
                    ],
                ]]);
            }

            if (str_contains($request->url(), '/filing-history')) {
                return Http::response(['items' => []]);
            }

            return Http::response([], 404);
        });

        app(CompaniesHouseDetailsService::class)->load($prospect->fresh());

        $prospect->refresh();

        $this->assertCount(1, $prospect->companies_house_details['officers']);
        $this->assertSame('Farida Khanam Ali', $prospect->companies_house_details['officers'][0]['name']);
    }
}
