<?php

namespace App\Services;

use App\Enums\ProspectFinancialStatus;
use App\Models\Prospect;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Enriches a Prospect with a best-effort Companies House match.
 *
 * This is a qualification/risk signal, not a profitability score: most prospects
 * (sole traders, partnerships, and the bulk of small/micro limited companies) either
 * have no Companies House record at all, or file abridged accounts with no profit
 * and loss statement. See docs note in PR description for the reasoning — this only
 * surfaces company status, filing compliance, age, and registered charges.
 */
class CompaniesHouseLookupService
{
    /** Minimum combined match score (0-100) before we trust a name search result. */
    private const MATCH_THRESHOLD = 60;

    private const RECENTLY_INCORPORATED_DAYS = 365;

    public function check(Prospect $prospect): void
    {
        if (blank($this->apiKey())) {
            $prospect->update([
                'companies_house_status' => ProspectFinancialStatus::Caution->value,
                'companies_house_summary' => 'Companies House API key not configured — check skipped.',
                'companies_house_flags' => ['Companies House API key not configured'],
                'companies_house_checked_at' => now(),
            ]);

            return;
        }

        if (filled($prospect->registered_company_number)) {
            $this->checkByRegisteredNumber($prospect);

            return;
        }

        $searchName = filled($prospect->registered_company_name)
            ? (string) $prospect->registered_company_name
            : (string) $prospect->business_name;

        if (blank($searchName)) {
            return;
        }

        $viaRegisteredName = filled($prospect->registered_company_name);
        $candidates = $this->search($searchName);
        $match = $this->bestMatch($candidates, $prospect, $searchName);

        if ($match === null) {
            $prospect->update([
                'companies_house_number' => null,
                'companies_house_status' => ProspectFinancialStatus::NoMatch->value,
                'companies_house_summary' => 'No confident Companies House match — likely a sole trader or partnership.',
                'companies_house_flags' => ['No Companies House match — likely sole trader or partnership'],
                'raw_companies_house_payload' => null,
                'companies_house_checked_at' => now(),
            ]);

            return;
        }

        $this->applyMatch($prospect, $match['company_number'], $viaRegisteredName);
    }

    private function checkByRegisteredNumber(Prospect $prospect): void
    {
        $companyNumber = (string) $prospect->registered_company_number;
        $profile = $this->profile($companyNumber);

        if ($profile === null) {
            $prospect->update([
                'companies_house_number' => $companyNumber,
                'companies_house_status' => ProspectFinancialStatus::Caution->value,
                'companies_house_summary' => 'Registered company number not found on Companies House — verify manually.',
                'companies_house_flags' => ['Registered company number not found on Companies House'],
                'raw_companies_house_payload' => null,
                'companies_house_checked_at' => now(),
            ]);

            return;
        }

        $this->applyMatch($prospect, $companyNumber, false);
    }

    private function applyMatch(Prospect $prospect, string $companyNumber, bool $viaRegisteredName): void
    {
        $profile = $this->profile($companyNumber);

        if ($profile === null) {
            $prospect->update([
                'companies_house_number' => $companyNumber,
                'companies_house_status' => ProspectFinancialStatus::Caution->value,
                'companies_house_summary' => 'Matched a company number but could not fetch its profile — verify manually.',
                'companies_house_flags' => ['Could not fetch Companies House profile'],
                'companies_house_checked_at' => now(),
            ]);

            return;
        }

        $chargeCount = $this->chargeCount($companyNumber);
        [$status, $flags, $summary] = $this->assess($profile, $chargeCount);

        if ($viaRegisteredName) {
            $summary = "Matched via registered company name — {$summary}";
        }

        $prospect->update([
            'companies_house_number' => $companyNumber,
            'companies_house_status' => $status->value,
            'companies_house_summary' => $summary,
            'companies_house_flags' => $flags,
            'raw_companies_house_payload' => array_merge($profile, ['charge_count' => $chargeCount]),
            'companies_house_checked_at' => now(),
        ]);
    }

    /**
     * @return list<array{company_number: string, title: string, address_snippet: ?string}>
     */
    private function search(string $name): array
    {
        $response = Http::withBasicAuth($this->apiKey(), '')
            ->timeout(10)
            ->get("{$this->baseUrl()}/search/companies", [
                'q' => $name,
                'items_per_page' => 10,
            ]);

        if ($response->failed()) {
            Log::warning('CompaniesHouseLookupService: search failed', [
                'status' => $response->status(),
                'name' => $name,
            ]);

            return [];
        }

        $items = $response->json('items') ?? [];

        return array_values(array_map(fn (array $item) => [
            'company_number' => (string) ($item['company_number'] ?? ''),
            'title' => (string) ($item['title'] ?? ''),
            'address_snippet' => $item['address_snippet'] ?? null,
        ], array_filter($items, fn ($item) => filled($item['company_number'] ?? null))));
    }

    /**
     * @param  list<array{company_number: string, title: string, address_snippet: ?string}>  $candidates
     * @return array{company_number: string, title: string, address_snippet: ?string}|null
     */
    private function bestMatch(array $candidates, Prospect $prospect, ?string $searchName = null): ?array
    {
        if ($candidates === []) {
            return null;
        }

        $postcode = $this->extractPostcode((string) $prospect->address);
        $nameForMatch = $searchName ?? (string) $prospect->business_name;
        $best = null;
        $bestScore = 0;

        foreach ($candidates as $candidate) {
            $score = $this->matchScore($candidate, $nameForMatch, $postcode);

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $candidate;
            }
        }

        if ($best === null || $bestScore < self::MATCH_THRESHOLD) {
            return null;
        }

        return $best;
    }

    /**
     * @param  array{company_number: string, title: string, address_snippet: ?string}  $candidate
     */
    private function matchScore(array $candidate, string $businessName, ?string $postcode): int
    {
        similar_text(
            Str::lower($this->stripCompanySuffix($businessName)),
            Str::lower($this->stripCompanySuffix($candidate['title'])),
            $namePercent,
        );

        $score = (int) round($namePercent * 0.7);

        if ($postcode !== null && $candidate['address_snippet'] !== null
            && Str::contains(Str::upper($candidate['address_snippet']), Str::upper($postcode))) {
            $score += 30;
        }

        return min($score, 100);
    }

    private function stripCompanySuffix(string $name): string
    {
        return trim((string) preg_replace(
            '/\b(ltd|limited|llp|plc|the)\b/i',
            '',
            $name,
        ));
    }

    private function extractPostcode(string $address): ?string
    {
        if (preg_match('/[A-Z]{1,2}\d[A-Z\d]?\s*\d[A-Z]{2}/i', $address, $matches)) {
            return Str::upper(trim($matches[0]));
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function profile(string $companyNumber): ?array
    {
        $response = Http::withBasicAuth($this->apiKey(), '')
            ->timeout(10)
            ->get("{$this->baseUrl()}/company/{$companyNumber}");

        if ($response->failed()) {
            Log::warning('CompaniesHouseLookupService: profile fetch failed', [
                'status' => $response->status(),
                'company_number' => $companyNumber,
            ]);

            return null;
        }

        return $response->json() ?? [];
    }

    private function chargeCount(string $companyNumber): int
    {
        $response = Http::withBasicAuth($this->apiKey(), '')
            ->timeout(10)
            ->get("{$this->baseUrl()}/company/{$companyNumber}/charges");

        if ($response->failed()) {
            // 404 means no charges registered — not an error condition for this endpoint.
            return 0;
        }

        return (int) ($response->json('total_count') ?? 0);
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return array{0: ProspectFinancialStatus, 1: list<string>, 2: string}
     */
    private function assess(array $profile, int $chargeCount): array
    {
        $flags = [];
        $companyStatus = (string) ($profile['company_status'] ?? 'unknown');
        $dissolvedStatuses = ['dissolved', 'liquidation', 'administration', 'receivership', 'converted-closed', 'removed'];

        if (in_array($companyStatus, $dissolvedStatuses, true)) {
            $label = str_replace('-', ' ', $companyStatus);
            $flags[] = "Company status: {$label} — exclude from outreach";

            return [
                ProspectFinancialStatus::Dissolved,
                $flags,
                "Companies House shows this company as {$label} — not suitable for outreach.",
            ];
        }

        $incorporatedRecently = false;
        $dateOfCreation = $profile['date_of_creation'] ?? null;

        if (is_string($dateOfCreation) && $dateOfCreation !== '') {
            $age = (int) now()->diffInDays(Carbon::parse($dateOfCreation), true);

            if ($age < self::RECENTLY_INCORPORATED_DAYS) {
                $incorporatedRecently = true;
                $flags[] = "Incorporated {$age} days ago — unlikely to have outreach budget yet";
            } else {
                $years = intdiv($age, 365);
                $flags[] = "Active company, incorporated {$years}+ years ago";
            }
        }

        $accountsOverdue = (bool) ($profile['accounts']['overdue'] ?? false);

        if ($accountsOverdue) {
            $flags[] = 'Accounts overdue at Companies House — possible cash flow or compliance issue';
        }

        if ($chargeCount > 0) {
            $flags[] = "{$chargeCount} charge(s) registered against company — existing secured debt";
        }

        $status = ($incorporatedRecently || $accountsOverdue)
            ? ProspectFinancialStatus::Caution
            : ProspectFinancialStatus::Matched;

        $summary = match (true) {
            $accountsOverdue => 'Matched on Companies House — accounts are overdue, worth checking before investing outreach time.',
            $incorporatedRecently => 'Matched on Companies House — very recently incorporated, may not have budget yet.',
            default => 'Matched on Companies House — active, no filing or status red flags.',
        };

        return [$status, $flags, $summary];
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
