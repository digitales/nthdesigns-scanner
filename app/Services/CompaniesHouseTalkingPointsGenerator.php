<?php

namespace App\Services;

use Illuminate\Support\Carbon;

class CompaniesHouseTalkingPointsGenerator
{
    private const MAX_POINTS = 5;

    /**
     * @param  array<string, mixed>  $details
     * @param  list<string>  $flags
     * @return list<string>
     */
    public function generate(array $details, array $flags): array
    {
        $points = [];

        foreach ($details['recent_activity'] ?? [] as $activity) {
            $point = $this->officerActivityPoint($activity);

            if ($point !== null) {
                $points[] = $point;
            }
        }

        foreach ($flags as $flag) {
            $point = $this->flagPoint($flag);

            if ($point !== null) {
                $points[] = $point;
            }
        }

        $financialPoint = $this->financialPoint($details['financials'] ?? []);
        if ($financialPoint !== null) {
            $points[] = $financialPoint;
        }

        $incorporationPoint = $this->incorporationPoint($details['company_snapshot'] ?? []);
        if ($incorporationPoint !== null) {
            $points[] = $incorporationPoint;
        }

        return array_slice(array_values(array_unique($points)), 0, self::MAX_POINTS);
    }

    /**
     * @param  array<string, mixed>  $activity
     */
    private function officerActivityPoint(array $activity): ?string
    {
        if (($activity['category'] ?? '') !== 'officers') {
            return null;
        }

        $date = $activity['date'] ?? null;

        if (! is_string($date) || $date === '') {
            return null;
        }

        $daysAgo = (int) now()->diffInDays(Carbon::parse($date), true);

        if ($daysAgo > 365) {
            return null;
        }

        $description = strtolower((string) ($activity['description'] ?? ''));

        if (str_contains($description, 'appoint')) {
            return 'Director appointed within the last year — possible leadership change';
        }

        if (str_contains($description, 'resign') || str_contains($description, 'terminat')) {
            return 'Director change within the last year — worth noting before outreach';
        }

        return null;
    }

    private function flagPoint(string $flag): ?string
    {
        $lower = strtolower($flag);

        if (str_contains($lower, 'accounts overdue')) {
            return 'Accounts overdue at Companies House — possible cash flow or compliance pressure';
        }

        if (str_contains($lower, 'charge(s) registered')) {
            return 'Secured charges on file — company may already have debt commitments';
        }

        if (str_contains($lower, 'incorporated') && str_contains($lower, 'days ago')) {
            return 'Recently incorporated — may still be building budget and operations';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $financials
     */
    private function financialPoint(array $financials): ?string
    {
        if (($financials['status'] ?? '') !== 'available') {
            return null;
        }

        $parts = [];

        if (filled($financials['turnover'] ?? null)) {
            $parts[] = 'turnover ~'.$this->formatMoney((int) $financials['turnover']);
        }

        if (filled($financials['profit_before_tax'] ?? null)) {
            $parts[] = 'profit ~'.$this->formatMoney((int) $financials['profit_before_tax']);
        }

        if ($parts === []) {
            return null;
        }

        return 'Latest filed accounts show '.implode(' and ', $parts);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function incorporationPoint(array $snapshot): ?string
    {
        $incorporatedOn = $snapshot['incorporated_on'] ?? null;

        if (! is_string($incorporatedOn) || $incorporatedOn === '') {
            return null;
        }

        $days = (int) now()->diffInDays(Carbon::parse($incorporatedOn), true);

        if ($days >= 365) {
            return null;
        }

        return 'Incorporated within the last year — early-stage company';
    }

    private function formatMoney(int $amount): string
    {
        if ($amount >= 1_000_000) {
            $millions = $amount / 1_000_000;

            return '£'.rtrim(rtrim(number_format($millions, 1), '0'), '.').'m';
        }

        if ($amount >= 100_000) {
            $thousands = $amount / 1_000;

            return '£'.rtrim(rtrim(number_format($thousands, 0), '0'), '.').'k';
        }

        return '£'.number_format($amount);
    }
}
