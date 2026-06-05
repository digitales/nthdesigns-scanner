<?php

namespace App\Queries;

use App\Models\Prospect;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ProspectListQuery
{
    private Builder $query;

    public function __construct(private User $user)
    {
        $this->query = Prospect::query()
            ->whereHas('search', fn (Builder $q) => $q->where('user_id', $this->user->id))
            ->with(['search', 'report'])
            ->with(['outreachEmails' => fn ($q) => $q->latest()->limit(1)])
            ->orderByDesc('combined_score');
    }

    public function apply(array $filters): self
    {
        if (! empty($filters['from'])) {
            $this->query->whereDate('prospects.created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $this->query->whereDate('prospects.created_at', '<=', $filters['to']);
        }

        if (! empty($filters['niche'])) {
            $this->query->whereHas('search', fn (Builder $q) => $q
                ->where('niche', 'like', '%'.$filters['niche'].'%'));
        }

        if (! empty($filters['city'])) {
            $this->query->whereHas('search', fn (Builder $q) => $q
                ->where('city', 'like', '%'.$filters['city'].'%'));
        }

        if (! empty($filters['scan_type'])) {
            $this->query->whereHas('search', fn (Builder $q) => $q
                ->where('scan_type', $filters['scan_type']));
        }

        if (isset($filters['min_score']) && $filters['min_score'] !== '' && $filters['min_score'] !== null) {
            $this->query->where('combined_score', '>=', (int) $filters['min_score']);
        }

        if (! empty($filters['dominant_angle'])) {
            $this->query->where('dominant_angle', $filters['dominant_angle']);
        }

        if (! empty($filters['warm'])) {
            $this->applyWarmScope();
        }

        return $this;
    }

    public function query(): Builder
    {
        return $this->query;
    }

    public function warmLeads(int $limit = 10): Collection
    {
        return (new self($this->user))
            ->apply(['warm' => true])
            ->query()
            ->limit($limit)
            ->get();
    }

    private function applyWarmScope(): void
    {
        $this->query
            ->whereHas('report', fn (Builder $q) => $q->whereNotNull('viewed_at'))
            ->whereHas('outreachEmails', fn (Builder $q) => $q->whereNotNull('sent_at'))
            ->whereDoesntHave('outreachEmails', fn (Builder $q) => $q->where('response_received', true));
    }
}
