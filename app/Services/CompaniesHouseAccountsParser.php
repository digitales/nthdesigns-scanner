<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CompaniesHouseAccountsParser
{
    /** @var list<string> */
    private const TURNOVER_TAGS = ['TurnoverRevenue', 'Revenue', 'Turnover'];

    /** @var list<string> */
    private const PROFIT_TAGS = ['ProfitLossOnOrdinaryActivitiesBeforeTax', 'ProfitLoss'];

    /** @var list<string> */
    private const NET_ASSETS_TAGS = ['NetAssetsLiabilities', 'TotalAssetsLessCurrentLiabilities'];

    /** @var list<string> */
    private const EMPLOYEE_TAGS = ['AverageNumberEmployeesDuringPeriod', 'NumberEmployees'];

    /** @var list<string> */
    private const PERIOD_END_TAGS = ['PeriodEnd', 'BalanceSheetDate'];

    /**
     * @param  list<array<string, mixed>>  $filings
     * @return array<string, mixed>|null
     */
    public function findLatestElectronicAccountsFiling(array $filings): ?array
    {
        foreach ($filings as $filing) {
            if (($filing['category'] ?? '') !== 'accounts') {
                continue;
            }

            if (($filing['paper_filed'] ?? false) === true) {
                continue;
            }

            if (blank($filing['links']['document_metadata'] ?? null)) {
                continue;
            }

            return $filing;
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $filings
     */
    public function latestAccountsIsPaperFiled(array $filings): bool
    {
        foreach ($filings as $filing) {
            if (($filing['category'] ?? '') !== 'accounts') {
                continue;
            }

            return ($filing['paper_filed'] ?? false) === true;
        }

        return false;
    }

    /**
     * @return array{
     *     turnover: ?int,
     *     profit_before_tax: ?int,
     *     net_assets: ?int,
     *     employees: ?int,
     *     period_end: ?string
     * }
     */
    public function parse(string $html): array
    {
        return [
            'turnover' => $this->extractFractionFromHtml($html, self::TURNOVER_TAGS),
            'profit_before_tax' => $this->extractFractionFromHtml($html, self::PROFIT_TAGS),
            'net_assets' => $this->extractFractionFromHtml($html, self::NET_ASSETS_TAGS),
            'employees' => $this->extractNumericFromHtml($html, self::EMPLOYEE_TAGS),
            'period_end' => $this->extractDateFromHtml($html, self::PERIOD_END_TAGS),
        ];
    }

    /**
     * @param  list<string>  $tagNames
     */
    private function extractFractionFromHtml(string $html, array $tagNames): ?int
    {
        foreach ($tagNames as $tagName) {
            $pattern = '/<(?:ix:)?nonFraction\b([^>]*\bname="[^"]*'.preg_quote($tagName, '/').'"[^>]*)>([^<]*)<\/(?:ix:)?nonFraction>/i';

            if (! preg_match($pattern, $html, $matches)) {
                continue;
            }

            $raw = trim($matches[2]);

            if ($raw === '') {
                continue;
            }

            $scale = 0;

            if (preg_match('/\bscale="(-?\d+)"/i', $matches[1], $scaleMatch)) {
                $scale = (int) $scaleMatch[1];
            }

            $value = (float) str_replace(',', '', $raw);

            return (int) round($value * (10 ** $scale));
        }

        return null;
    }

    /**
     * @param  list<string>  $tagNames
     */
    private function extractNumericFromHtml(string $html, array $tagNames): ?int
    {
        foreach ($tagNames as $tagName) {
            $pattern = '/<(?:ix:)?nonNumeric\b[^>]*\bname="[^"]*'.preg_quote($tagName, '/').'"[^>]*>([^<]*)<\/(?:ix:)?nonNumeric>/i';

            if (! preg_match($pattern, $html, $matches)) {
                continue;
            }

            $raw = trim($matches[1]);

            if ($raw !== '' && is_numeric($raw)) {
                return (int) round((float) $raw);
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $tagNames
     */
    private function extractDateFromHtml(string $html, array $tagNames): ?string
    {
        foreach ($tagNames as $tagName) {
            $pattern = '/<(?:ix:)?nonNumeric\b[^>]*\bname="[^"]*'.preg_quote($tagName, '/').'"[^>]*>([^<]*)<\/(?:ix:)?nonNumeric>/i';

            if (preg_match($pattern, $html, $matches)) {
                $raw = trim($matches[1]);

                if ($raw !== '') {
                    return $raw;
                }
            }
        }

        return null;
    }

    public function downloadDocument(string $documentMetadataUrl, string $apiKey): ?string
    {
        $metadataResponse = Http::withBasicAuth($apiKey, '')
            ->timeout(15)
            ->get($documentMetadataUrl);

        if ($metadataResponse->failed()) {
            Log::warning('CompaniesHouseAccountsParser: document metadata fetch failed', [
                'status' => $metadataResponse->status(),
                'url' => $documentMetadataUrl,
            ]);

            return null;
        }

        $documentUrl = $metadataResponse->json('links.document') ?? null;

        if (! is_string($documentUrl) || $documentUrl === '') {
            return null;
        }

        $documentResponse = Http::withBasicAuth($apiKey, '')
            ->timeout(20)
            ->withHeaders(['Accept' => 'application/xhtml+xml'])
            ->get($documentUrl);

        if ($documentResponse->failed()) {
            Log::warning('CompaniesHouseAccountsParser: document download failed', [
                'status' => $documentResponse->status(),
            ]);

            return null;
        }

        return $documentResponse->body();
    }
}
