<?php

namespace App\Services\KeywordPlanner;

use Illuminate\Http\UploadedFile;

class KeywordPlannerCsvImporter
{
    private const KEYWORD_COLUMN = 'Keyword';

    private const HIGH_BID_COLUMN = 'Top of page bid (high range)';

    /**
     * @var list<string>
     */
    private const NON_COMMERCIAL_PATTERNS = [
        'graduate',
        'grad',
        'internship',
        'intern',
        'career',
        'careers',
        'jobs',
        'job',
        'scheme',
        'schemes',
        'vacancy',
        'vacancies',
        'recruitment',
        'hiring',
        'apprentice',
    ];

    public function import(UploadedFile $file): KeywordPlannerImportResult
    {
        $contents = file_get_contents($file->getRealPath());

        if ($contents === false) {
            throw new KeywordPlannerImportException(
                'Please upload a CSV file from Keyword Planner (max 2 MB).',
            );
        }

        $contents = ltrim($contents, "\xEF\xBB\xBF");
        $lines = preg_split('/\R/', $contents) ?: [];

        if (count($lines) < 3) {
            throw new KeywordPlannerImportException(
                'This doesn\'t look like a Keyword Planner export. Export from Discover new keywords in Google Ads.',
            );
        }

        $headerLine = $lines[2];
        $delimiter = str_contains($headerLine, "\t") ? "\t" : null;

        if ($delimiter === null) {
            throw new KeywordPlannerImportException(
                'This doesn\'t look like a Keyword Planner export. Export from Discover new keywords in Google Ads.',
            );
        }

        $headers = str_getcsv($headerLine, $delimiter);
        $keywordIndex = array_search(self::KEYWORD_COLUMN, $headers, true);
        $highBidIndex = array_search(self::HIGH_BID_COLUMN, $headers, true);

        if ($keywordIndex === false || $highBidIndex === false) {
            throw new KeywordPlannerImportException(
                'This doesn\'t look like a Keyword Planner export. Export from Discover new keywords in Google Ads.',
            );
        }

        $allKeywords = [];
        $commercialBids = [];

        for ($i = 3, $count = count($lines); $i < $count; $i++) {
            $line = trim($lines[$i]);

            if ($line === '') {
                continue;
            }

            $columns = str_getcsv($line, $delimiter);
            $keyword = trim($columns[$keywordIndex] ?? '');

            if ($keyword === '') {
                continue;
            }

            $highBid = $this->parseBid($columns[$highBidIndex] ?? null);

            if ($highBid === null) {
                continue;
            }

            $allKeywords[] = $keyword;

            if (! $this->isNonCommercial($keyword)) {
                $commercialBids[] = $highBid;
            }
        }

        if ($allKeywords === []) {
            throw new KeywordPlannerImportException(
                'No keywords with bid data found in this file.',
            );
        }

        if ($commercialBids === []) {
            throw new KeywordPlannerImportException(
                'No commercial keywords with bid data found. Check your Keyword Planner seeds.',
            );
        }

        sort($commercialBids);
        $median = $commercialBids[(int) floor((count($commercialBids) - 1) / 2)];

        return new KeywordPlannerImportResult(
            benchmark: round($median * 2) / 2,
            keywords: array_values(array_unique($allKeywords)),
            commercialCount: count($commercialBids),
            totalCount: count(array_unique($allKeywords)),
        );
    }

    private function parseBid(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $bid = (float) $value;

        return $bid > 0 ? $bid : null;
    }

    private function isNonCommercial(string $keyword): bool
    {
        foreach (self::NON_COMMERCIAL_PATTERNS as $pattern) {
            if (preg_match('/\b'.preg_quote($pattern, '/').'\b/i', $keyword) === 1) {
                return true;
            }
        }

        return false;
    }
}
