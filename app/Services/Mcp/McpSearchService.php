<?php

namespace App\Services\Mcp;

use App\Models\Search;
use App\Models\User;
use App\Services\ReportBuilderService;

class McpSearchService
{
    public function __construct(private ReportBuilderService $reportBuilder) {}

    /**
     * @return array{searches: list<array<string, mixed>>}
     */
    public function listSearches(User $user, int $limit = 10, ?string $status = null): array
    {
        $limit = max(1, min(50, $limit));

        $query = $user->searches()->latest();

        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }

        return [
            'searches' => $query->limit($limit)->get()
                ->map(fn (Search $search) => $this->mapSearchSummary($search))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getSearch(User $user, int $searchId, bool $includeProspects = false): array
    {
        $search = $this->findAuthorizedSearch($user, $searchId);
        $search->loadCount('prospects');

        $payload = [
            'search' => $this->mapSearchDetail($search),
            'progress' => $this->buildProgress($search),
            'app_url' => route('searches.show', $search),
        ];

        if ($includeProspects) {
            $payload['prospects'] = $this->mapProspects($search);
        }

        return $payload;
    }

    /**
     * @return array{search_id: int, prospects: list<array<string, mixed>>}
     */
    public function listSearchProspects(User $user, int $searchId): array
    {
        $search = $this->findAuthorizedSearch($user, $searchId);

        return [
            'search_id' => $search->id,
            'prospects' => $this->mapProspects($search),
        ];
    }

    private function findAuthorizedSearch(User $user, int $searchId): Search
    {
        if ($searchId < 1) {
            throw new \InvalidArgumentException('search_id is required.');
        }

        $search = Search::query()->find($searchId);

        if ($search === null || $user->cannot('view', $search)) {
            throw new \InvalidArgumentException('Search not found.');
        }

        return $search;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapSearchSummary(Search $search): array
    {
        return [
            'id' => $search->id,
            'source' => $search->source,
            'submitted_url' => $search->submitted_url,
            'niche' => $search->niche,
            'city' => $search->city,
            'status' => $search->status,
            'total_found' => $search->total_found,
            'created_at' => $search->created_at->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapSearchDetail(Search $search): array
    {
        return array_merge($this->mapSearchSummary($search), [
            'country' => $search->country,
            'scan_type' => $search->scan_type,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildProgress(Search $search): array
    {
        $prospects = $search->prospects()->with('report')->get();

        $auditStatusCounts = [
            'pending' => 0,
            'complete' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        $reportsReady = 0;

        foreach ($prospects as $prospect) {
            $status = $prospect->audit_status ?? 'pending';
            if (array_key_exists($status, $auditStatusCounts)) {
                $auditStatusCounts[$status]++;
            }

            if ($prospect->report !== null) {
                $reportsReady++;
            }
        }

        return [
            'prospects_total' => $prospects->count(),
            'audit_status_counts' => $auditStatusCounts,
            'reports_ready' => $reportsReady,
            'search_complete' => in_array($search->status, ['complete', 'failed'], true),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mapProspects(Search $search): array
    {
        $prospects = $search->prospects()
            ->with([
                'report',
                'auditJobs' => fn ($q) => $q->where('status', 'failed')->latest()->limit(1),
            ])
            ->orderByDesc('combined_score')
            ->get();

        return $prospects->map(fn ($prospect) => $this->mapProspect($prospect))->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function mapProspect($prospect): array
    {
        $cms = $this->reportBuilder->cmsForProspect($prospect);

        return [
            'id' => $prospect->id,
            'business_name' => $prospect->business_name,
            'combined_score' => $prospect->combined_score,
            'gbp_score' => $prospect->gbp_score,
            'a11y_score' => $prospect->a11y_score,
            'performance_score' => $prospect->performance_score,
            'dominant_angle' => $prospect->dominant_angle,
            'audit_status' => $prospect->audit_status,
            'audit_error' => $prospect->auditJobs->first()?->error_message,
            'gbp_flags' => $prospect->gbp_flags ?? [],
            'a11y_flags' => $prospect->a11y_flags ?? [],
            'report_ready' => $prospect->report !== null,
            'cms_badge' => $cms['badge'] ?? null,
            'cms_pending' => $cms['pending'] ?? false,
        ];
    }
}
