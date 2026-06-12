<?php

namespace App\Services\Outreach;

use App\Http\Resources\OutreachEmailResource;
use App\Models\OutreachEmail;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class OutreachQueueLoader
{
    public function selections(User $user, bool $bookedOnly, bool $withReport = false): EloquentCollection
    {
        $relations = ['prospect.search', 'prospect.report.booking'];

        if ($withReport) {
            $relations[] = 'prospect.report';
        }

        $query = $user->outreachSelections()
            ->with($relations)
            ->orderBy('created_at')
            ->limit((int) config('scanner.outreach_queue_max', 200));

        if ($bookedOnly) {
            $query->whereHas('prospect.report.booking');
        }

        return $query->get();
    }

    /**
     * @param  Collection<int, int>  $prospectIds
     * @return array<int, list<array<string, mixed>>>
     */
    public function latestEmailsByProspect(User $user, Collection $prospectIds): array
    {
        if ($prospectIds->isEmpty()) {
            return [];
        }

        $latestIds = OutreachEmail::query()
            ->selectRaw('MAX(id) as id')
            ->where('user_id', $user->id)
            ->whereIn('prospect_id', $prospectIds)
            ->groupBy('prospect_id')
            ->pluck('id');

        if ($latestIds->isEmpty()) {
            return [];
        }

        return OutreachEmail::query()
            ->with('prospect')
            ->whereIn('id', $latestIds)
            ->get()
            ->groupBy('prospect_id')
            ->map(fn ($group) => $group
                ->map(fn (OutreachEmail $email) => OutreachEmailResource::format($email))
                ->values()
                ->all())
            ->all();
    }
}
