<?php

namespace App\Services\Mcp;

use App\Http\Resources\SearchProspectResource;
use App\Http\Resources\SearchSummaryMapper;
use App\Models\Search;
use App\Models\User;
use App\Services\ProgressFlowService;
use App\Services\ReportBuilderService;
use Illuminate\Support\Collection;

class McpSearchService
{
    public function __construct(
        private ReportBuilderService $reportBuilder,
        private ProgressFlowService $progressFlow,
    ) {}

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
                ->map(fn (Search $search) => SearchSummaryMapper::format($search, 'iso'))
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
        $prospects = $this->loadProspects($search);
        $search->loadCount('prospects');
        $flow = $this->progressFlow->searchFlow($search, $prospects);

        $payload = [
            'search' => SearchSummaryMapper::detail($search),
            'progress' => $this->buildProgress($search, $prospects),
            'progress_flow' => $flow,
            'app_url' => route('searches.show', $search),
        ];

        if ($includeProspects) {
            $payload['prospects'] = $this->mapProspects($search, $prospects);
        }

        return $payload;
    }

    /**
     * @return array{search_id: int, prospects: list<array<string, mixed>>}
     */
    public function listSearchProspects(User $user, int $searchId): array
    {
        $search = $this->findAuthorizedSearch($user, $searchId);
        $prospects = $this->loadProspects($search);

        return [
            'search_id' => $search->id,
            'prospects' => $this->mapProspects($search, $prospects),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getSearchProgressFlow(User $user, int $searchId, bool $includeProspects = true): array
    {
        $search = $this->findAuthorizedSearch($user, $searchId);
        $prospects = $this->loadProspects($search);
        $flow = $this->progressFlow->searchFlow($search, $prospects);

        $payload = [
            'search_id' => $search->id,
            'status' => $search->status,
            'progress_flow' => $flow,
            'app_url' => route('searches.show', $search),
        ];

        if ($includeProspects) {
            $payload['prospects'] = $prospects->map(function ($prospect) use ($search) {
                return [
                    'id' => $prospect->id,
                    'business_name' => $prospect->business_name,
                    'audit_status' => $prospect->audit_status,
                    'report_ready' => $prospect->report !== null,
                    'progress_flow' => $this->progressFlow->prospectFlow($prospect, $search),
                ];
            })->values()->all();
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function watchSearchProgress(User $user, int $searchId, int $timeoutSeconds = 45, bool $includeProspects = false): array
    {
        $timeoutSeconds = max(5, min(45, $timeoutSeconds));

        return [
            'watch' => [
                'search_id' => $searchId,
                'timeout_seconds' => $timeoutSeconds,
                'include_prospects' => $includeProspects,
            ],
            'snapshot' => $this->getSearchProgressFlow($user, $searchId, $includeProspects),
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
    private function buildProgress(Search $search, Collection $prospects): array
    {
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
    private function mapProspects(Search $search, Collection $prospects): array
    {
        return $prospects
            ->map(fn ($prospect) => SearchProspectResource::forMcp(
                $prospect,
                $search,
                $this->reportBuilder,
                $this->progressFlow,
            ))
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, mixed>
     */
    private function loadProspects(Search $search): Collection
    {
        return $search->prospects()
            ->with([
                'report',
                'auditJobs' => fn ($q) => $q->latest()->limit(1),
            ])
            ->orderByDesc('combined_score')
            ->get();
    }
}
