<?php

namespace App\Services;

use App\Models\Prospect;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CompaniesHouseDetailsService
{
    private const FILING_HISTORY_LIMIT = 15;

    public function __construct(
        private CompaniesHouseAccountsParser $accountsParser,
        private CompaniesHouseTalkingPointsGenerator $talkingPoints,
    ) {}

    public function load(Prospect $prospect): void
    {
        $companyNumber = (string) $prospect->companies_house_number;

        if (blank($companyNumber) || blank($this->apiKey())) {
            return;
        }

        $profile = $prospect->raw_companies_house_payload ?? [];
        $filingItems = $this->fetchFilingHistory($companyNumber);
        $officers = $this->fetchOfficers($companyNumber);
        $financials = $this->buildFinancials($companyNumber, $filingItems);

        $details = [
            'company_snapshot' => $this->buildSnapshot($profile),
            'recent_activity' => $this->normalizeActivity($filingItems),
            'officers' => $officers,
            'financials' => $financials,
            'talking_points' => [],
            'links' => $this->buildLinks($companyNumber, $filingItems, $financials),
        ];

        $details['talking_points'] = $this->talkingPoints->generate(
            $details,
            $prospect->companies_house_flags ?? [],
        );

        $prospect->update([
            'companies_house_details' => $details,
            'companies_house_details_loaded_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return array<string, mixed>
     */
    private function buildSnapshot(array $profile): array
    {
        $accounts = is_array($profile['accounts'] ?? null) ? $profile['accounts'] : [];
        $lastAccounts = is_array($accounts['last_accounts'] ?? null) ? $accounts['last_accounts'] : [];
        $nextAccounts = is_array($accounts['next_accounts'] ?? null) ? $accounts['next_accounts'] : [];

        return [
            'company_type' => $profile['type'] ?? null,
            'registered_office' => $this->formatAddress($profile['registered_office_address'] ?? null),
            'sic_codes' => array_values(array_filter(
                array_map(
                    fn ($code) => is_string($code) ? $code : ($code['code'] ?? null),
                    $profile['sic_codes'] ?? [],
                ),
            )),
            'incorporated_on' => $profile['date_of_creation'] ?? null,
            'accounts' => [
                'next_due' => $nextAccounts['due_on'] ?? ($accounts['next_due'] ?? null),
                'last_made_up_to' => $lastAccounts['made_up_to'] ?? null,
                'last_type' => $lastAccounts['type'] ?? null,
                'overdue' => (bool) ($accounts['overdue'] ?? ($nextAccounts['overdue'] ?? false)),
            ],
        ];
    }

    /**
     * @param  mixed  $address
     */
    private function formatAddress($address): ?string
    {
        if (! is_array($address)) {
            return null;
        }

        $parts = array_filter([
            $address['address_line_1'] ?? null,
            $address['address_line_2'] ?? null,
            $address['locality'] ?? null,
            $address['region'] ?? null,
            $address['postal_code'] ?? null,
        ]);

        return $parts === [] ? null : implode(', ', $parts);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchFilingHistory(string $companyNumber): array
    {
        $response = Http::withBasicAuth($this->apiKey(), '')
            ->timeout(10)
            ->get("{$this->baseUrl()}/company/{$companyNumber}/filing-history", [
                'items_per_page' => self::FILING_HISTORY_LIMIT,
            ]);

        if ($response->failed()) {
            Log::warning('CompaniesHouseDetailsService: filing history fetch failed', [
                'status' => $response->status(),
                'company_number' => $companyNumber,
            ]);

            return [];
        }

        return array_values($response->json('items') ?? []);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function normalizeActivity(array $items): array
    {
        return array_values(array_map(fn (array $item) => [
            'date' => $item['date'] ?? null,
            'category' => $item['category'] ?? null,
            'description' => $item['description'] ?? null,
            'type' => $item['type'] ?? null,
        ], $items));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchOfficers(string $companyNumber): array
    {
        $response = Http::withBasicAuth($this->apiKey(), '')
            ->timeout(10)
            ->get("{$this->baseUrl()}/company/{$companyNumber}/officers", [
                'register_view' => 'true',
            ]);

        if ($response->failed()) {
            Log::warning('CompaniesHouseDetailsService: officers fetch failed', [
                'status' => $response->status(),
                'company_number' => $companyNumber,
            ]);

            return [];
        }

        $items = $response->json('items') ?? [];

        return array_values(array_filter(array_map(function (array $item) {
            if (filled($item['resigned_on'] ?? null)) {
                return null;
            }

            return [
                'name' => $item['name'] ?? null,
                'role' => $this->officerRole($item),
                'appointed_on' => $item['appointed_on'] ?? null,
                'resigned_on' => null,
            ];
        }, $items)));
    }

    /**
     * @param  array<string, mixed>  $officer
     */
    private function officerRole(array $officer): ?string
    {
        $role = $officer['officer_role'] ?? null;

        if (! is_string($role)) {
            return null;
        }

        return str_replace('-', ' ', $role);
    }

    /**
     * @param  list<array<string, mixed>>  $filingItems
     * @return array<string, mixed>
     */
    private function buildFinancials(string $companyNumber, array $filingItems): array
    {
        $accountsFilings = array_values(array_filter(
            $filingItems,
            fn (array $item) => ($item['category'] ?? '') === 'accounts',
        ));

        if ($accountsFilings === []) {
            return $this->financialsShell('unavailable');
        }

        $electronicFiling = $this->accountsParser->findLatestElectronicAccountsFiling($accountsFilings);

        if ($electronicFiling === null) {
            return $this->financialsShell(
                $this->accountsParser->latestAccountsIsPaperFiled($accountsFilings) ? 'paper_filed' : 'unavailable',
            );
        }

        $metadataUrl = (string) $electronicFiling['links']['document_metadata'];
        $html = $this->accountsParser->downloadDocument($metadataUrl, (string) $this->apiKey());

        if ($html === null) {
            return $this->financialsShell('parse_failed', filing: $electronicFiling);
        }

        $parsed = $this->accountsParser->parse($html);
        $hasFigure = collect($parsed)
            ->only(['turnover', 'profit_before_tax', 'net_assets', 'employees'])
            ->contains(fn ($value) => $value !== null);

        if (! $hasFigure) {
            return $this->financialsShell('not_disclosed', filing: $electronicFiling);
        }

        return [
            'status' => 'available',
            'reason' => null,
            'period_end' => $parsed['period_end'] ?? ($electronicFiling['description'] ?? null),
            'filing_date' => $electronicFiling['date'] ?? null,
            'accounts_type' => $electronicFiling['type'] ?? null,
            'turnover' => $parsed['turnover'],
            'profit_before_tax' => $parsed['profit_before_tax'],
            'net_assets' => $parsed['net_assets'],
            'employees' => $parsed['employees'],
            'transaction_id' => $electronicFiling['transaction_id'] ?? null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filingItems
     * @param  array<string, mixed>  $financials
     * @return array<string, ?string>
     */
    private function buildLinks(string $companyNumber, array $filingItems, array $financials): array
    {
        $links = [
            'filing_history' => "https://find-and-update.company-information.service.gov.uk/company/{$companyNumber}/filing-history",
            'latest_accounts_document' => null,
        ];

        $transactionId = $financials['transaction_id'] ?? null;

        if (is_string($transactionId) && $transactionId !== '') {
            $links['latest_accounts_document'] = "https://find-and-update.company-information.service.gov.uk/company/{$companyNumber}/filing-history/{$transactionId}/document?format=xhtml&download=1";
        }

        return $links;
    }

    /**
     * @param  array<string, mixed>|null  $filing
     * @return array<string, mixed>
     */
    private function financialsShell(string $status, ?array $filing = null): array
    {
        return [
            'status' => $status,
            'reason' => null,
            'period_end' => null,
            'filing_date' => $filing['date'] ?? null,
            'accounts_type' => $filing['type'] ?? null,
            'turnover' => null,
            'profit_before_tax' => null,
            'net_assets' => null,
            'employees' => null,
            'transaction_id' => $filing['transaction_id'] ?? null,
        ];
    }

    private function apiKey(): ?string
    {
        return config('services.companies_house.key');
    }

    private function baseUrl(): string
    {
        return config('services.companies_house.base_url');
    }
}
