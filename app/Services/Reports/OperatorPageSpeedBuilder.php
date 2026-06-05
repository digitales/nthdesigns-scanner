<?php

namespace App\Services\Reports;

use App\Models\Prospect;
use Illuminate\Support\Carbon;

class OperatorPageSpeedBuilder
{
    /**
     * Shaped page speed breakdown for operator prospect detail. Null when section hidden.
     *
     * @return array<string, mixed>|null
     */
    public function build(Prospect $prospect): ?array
    {
        $prospect->loadMissing('search');

        if ($prospect->audit_status !== 'complete') {
            return null;
        }

        $payload = $prospect->raw_lighthouse_payload ?? [];
        $metrics = $payload['metrics'] ?? null;
        $opportunities = $payload['opportunities'] ?? null;

        if (! is_array($metrics) && ! is_array($opportunities)) {
            return null;
        }

        $auditedAt = $this->auditedAt($prospect);
        $a11yPayload = is_array($prospect->raw_a11y_payload) ? $prospect->raw_a11y_payload : [];

        return [
            'audited_at' => $auditedAt?->toIso8601String() ?? now()->toIso8601String(),
            'url' => $a11yPayload['url'] ?? $prospect->website_url ?? '',
            'metrics' => $this->shapeMetrics(is_array($metrics) ? $metrics : []),
            'opportunities' => $this->shapeOpportunities(is_array($opportunities) ? $opportunities : []),
            'has_detail' => true,
        ];
    }

    private function auditedAt(Prospect $prospect): ?Carbon
    {
        $completedJob = $prospect->relationLoaded('auditJobs')
            ? $prospect->auditJobs
                ->where('job_type', 'accessibility')
                ->where('status', 'complete')
                ->sortByDesc('completed_at')
                ->first()
            : null;

        return $completedJob?->completed_at ?? $prospect->updated_at;
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @return array{lcp: ?array{display: string, rating: string}, inp: ?array{display: string, rating: string}, cls: ?array{display: string, rating: string}, fcp: ?array{display: string, rating: string}}
     */
    private function shapeMetrics(array $metrics): array
    {
        $shape = fn (?array $row) => $row && isset($row['display'], $row['rating'])
            ? ['display' => (string) $row['display'], 'rating' => (string) $row['rating']]
            : null;

        return [
            'lcp' => $shape($metrics['lcp'] ?? null),
            'inp' => $shape($metrics['inp'] ?? null),
            'cls' => $shape($metrics['cls'] ?? null),
            'fcp' => $shape($metrics['fcp'] ?? null),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $opportunities
     * @return list<array{id: string, title: string, description: string, savings_display: string, savings_ms: int, highlight: bool}>
     */
    private function shapeOpportunities(array $opportunities): array
    {
        return array_values(array_map(function (array $row) {
            $savingsMs = (int) ($row['savings_ms'] ?? 0);
            $description = (string) ($row['description'] ?? '');

            return [
                'id' => (string) ($row['id'] ?? ''),
                'title' => (string) ($row['title'] ?? ''),
                'description' => mb_strlen($description) > 120 ? mb_substr($description, 0, 117).'...' : $description,
                'savings_display' => (string) ($row['savings_display'] ?? ''),
                'savings_ms' => $savingsMs,
                'highlight' => $savingsMs >= 500,
            ];
        }, $opportunities));
    }
}
