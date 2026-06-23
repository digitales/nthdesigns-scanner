<?php

namespace App\Services\SiteSearch;

use App\Contracts\SiteSearchProvider;
use App\Data\SiteSearchResult;
use App\Models\IgnoredProspect;
use App\Models\Prospect;
use App\Models\ProspectList;
use App\Models\ProspectNote;
use App\Models\ProspectReport;
use App\Models\ReportBooking;
use App\Models\Search;
use App\Models\Tag;
use App\Models\User;
use App\Support\LikeSearch;
use Illuminate\Database\Eloquent\Builder;

class SqlSiteSearchProvider implements SiteSearchProvider
{
    public function search(User $user, string $query): SiteSearchResult
    {
        $tokens = LikeSearch::tokens($query);
        $limit = (int) config('site_search.per_section_limit', 10);

        return SiteSearchResult::fromSections([
            $this->prospectsSection($user, $tokens, $limit),
            $this->scansSection($user, $tokens, $limit),
            $this->listsSection($user, $tokens, $limit),
            $this->tagsSection($user, $tokens, $limit),
            $this->notesSection($user, $tokens, $limit),
            $this->reportsSection($user, $tokens, $limit),
            $this->bookingsSection($user, $tokens, $limit),
        ]);
    }

    /**
     * @param  list<string>  $tokens
     * @return array{key: string, label: string, items: list<array{title: string, subtitle: string|null, href: string}>}
     */
    private function prospectsSection(User $user, array $tokens, int $limit): array
    {
        if ($tokens === []) {
            return $this->emptySection('prospects', 'Prospects');
        }

        $ignoredPlaceIds = IgnoredProspect::query()
            ->where('user_id', $user->id)
            ->pluck('place_id');

        $query = Prospect::query()
            ->whereHas('search', fn (Builder $q) => $q->where('user_id', $user->id))
            ->when(
                $ignoredPlaceIds->isNotEmpty(),
                fn (Builder $q) => $q->whereNotIn('place_id', $ignoredPlaceIds),
            )
            ->with('search:id,niche,city,user_id');

        LikeSearch::applyTokens($query, $tokens, [
            'business_name',
            'website_url',
            'email',
            'phone',
            'address',
            'place_id',
            'companies_house_number',
        ]);

        $items = $query
            ->latest('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (Prospect $prospect): array => [
                'title' => $prospect->business_name,
                'subtitle' => $prospect->website_url
                    ?: ($prospect->search?->city ? $prospect->search->city : null),
                'href' => route('prospects.show', $prospect),
            ])
            ->values()
            ->all();

        return [
            'key' => 'prospects',
            'label' => 'Prospects',
            'items' => $items,
        ];
    }

    /**
     * @param  list<string>  $tokens
     * @return array{key: string, label: string, items: list<array{title: string, subtitle: string|null, href: string}>}
     */
    private function scansSection(User $user, array $tokens, int $limit): array
    {
        if ($tokens === []) {
            return $this->emptySection('scans', 'Scans');
        }

        $query = $user->searches();
        LikeSearch::applyTokens($query, $tokens, [
            'niche',
            'city',
            'country',
            'submitted_url',
        ]);

        $items = $query
            ->latest('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (Search $search): array => [
                'title' => trim(implode(' · ', array_filter([$search->niche, $search->city]))),
                'subtitle' => $search->created_at->format('j M Y').' · '.$search->status->value,
                'href' => route('searches.show', $search),
            ])
            ->values()
            ->all();

        return [
            'key' => 'scans',
            'label' => 'Scans',
            'items' => $items,
        ];
    }

    /**
     * @param  list<string>  $tokens
     * @return array{key: string, label: string, items: list<array{title: string, subtitle: string|null, href: string}>}
     */
    private function listsSection(User $user, array $tokens, int $limit): array
    {
        if ($tokens === []) {
            return $this->emptySection('lists', 'Lists');
        }

        $query = $user->prospectLists()->withCount('items');
        LikeSearch::applyTokens($query, $tokens, ['name', 'description']);

        $items = $query
            ->latest('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (ProspectList $list): array => [
                'title' => $list->name,
                'subtitle' => $list->type->label().' · '.$list->items_count.' item'.($list->items_count === 1 ? '' : 's'),
                'href' => route('lists.show', $list),
            ])
            ->values()
            ->all();

        return [
            'key' => 'lists',
            'label' => 'Lists',
            'items' => $items,
        ];
    }

    /**
     * @param  list<string>  $tokens
     * @return array{key: string, label: string, items: list<array{title: string, subtitle: string|null, href: string}>}
     */
    private function tagsSection(User $user, array $tokens, int $limit): array
    {
        if ($tokens === []) {
            return $this->emptySection('tags', 'Tags');
        }

        $query = $user->tags()->withCount([
            'prospects as prospects_count' => fn (Builder $q) => $q->whereHas(
                'search',
                fn (Builder $sq) => $sq->where('user_id', $user->id),
            ),
        ]);

        LikeSearch::applyTokens($query, $tokens, ['name']);

        $items = $query
            ->latest('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (Tag $tag): array => [
                'title' => $tag->name,
                'subtitle' => $tag->prospects_count.' prospect'.($tag->prospects_count === 1 ? '' : 's'),
                'href' => route('lists.browse', ['tags' => [$tag->name]]),
            ])
            ->values()
            ->all();

        return [
            'key' => 'tags',
            'label' => 'Tags',
            'items' => $items,
        ];
    }

    /**
     * @param  list<string>  $tokens
     * @return array{key: string, label: string, items: list<array{title: string, subtitle: string|null, href: string}>}
     */
    private function notesSection(User $user, array $tokens, int $limit): array
    {
        if ($tokens === []) {
            return $this->emptySection('notes', 'Notes');
        }

        $query = ProspectNote::query()
            ->where('user_id', $user->id)
            ->whereHas('prospect.search', fn (Builder $q) => $q->where('user_id', $user->id))
            ->with('prospect:id,business_name');

        LikeSearch::applyTokens($query, $tokens, ['body']);

        $items = $query
            ->latest('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (ProspectNote $note): array => [
                'title' => str($note->body)->limit(120)->toString(),
                'subtitle' => $note->prospect?->business_name,
                'href' => route('prospects.show', $note->prospect_id),
            ])
            ->values()
            ->all();

        return [
            'key' => 'notes',
            'label' => 'Notes',
            'items' => $items,
        ];
    }

    /**
     * @param  list<string>  $tokens
     * @return array{key: string, label: string, items: list<array{title: string, subtitle: string|null, href: string}>}
     */
    private function reportsSection(User $user, array $tokens, int $limit): array
    {
        if ($tokens === []) {
            return $this->emptySection('reports', 'Reports');
        }

        $query = ProspectReport::query()
            ->whereHas('prospect.search', fn (Builder $q) => $q->where('user_id', $user->id))
            ->with('prospect:id,business_name');

        foreach ($tokens as $token) {
            $pattern = '%'.LikeSearch::escape($token).'%';
            $query->where(function (Builder $inner) use ($pattern): void {
                $inner->where(fn (Builder $tokenQuery) => LikeSearch::whereColumnLike($tokenQuery, 'token', $pattern, asText: true))
                    ->orWhereHas('prospect', fn (Builder $prospect) => LikeSearch::whereColumnLike(
                        $prospect,
                        'business_name',
                        $pattern,
                    ));
            });
        }

        $items = $query
            ->latest('updated_at')
            ->limit($limit)
            ->get()
            ->map(function (ProspectReport $report): array {
                $viewed = $report->viewed_at !== null ? 'Viewed' : 'Not viewed';

                return [
                    'title' => $report->prospect?->business_name ?? 'Report',
                    'subtitle' => $report->view_count.' views · '.$viewed,
                    'href' => route('prospects.show', $report->prospect_id),
                ];
            })
            ->values()
            ->all();

        return [
            'key' => 'reports',
            'label' => 'Reports',
            'items' => $items,
        ];
    }

    /**
     * @param  list<string>  $tokens
     * @return array{key: string, label: string, items: list<array{title: string, subtitle: string|null, href: string}>}
     */
    private function bookingsSection(User $user, array $tokens, int $limit): array
    {
        if ($tokens === []) {
            return $this->emptySection('bookings', 'Bookings');
        }

        $query = ReportBooking::query()
            ->whereHas('prospect.search', fn (Builder $q) => $q->where('user_id', $user->id))
            ->with('prospect:id,business_name');

        LikeSearch::applyTokens($query, $tokens, [
            'attendee_name',
            'attendee_email',
            'attendee_phone',
            'note',
        ]);

        $items = $query
            ->latest('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (ReportBooking $booking): array => [
                'title' => $booking->attendee_name,
                'subtitle' => trim(implode(' · ', array_filter([
                    $booking->prospect?->business_name,
                    $booking->starts_at?->format('j M Y g:ia'),
                ]))),
                'href' => route('bookings.index'),
            ])
            ->values()
            ->all();

        return [
            'key' => 'bookings',
            'label' => 'Bookings',
            'items' => $items,
        ];
    }

    /**
     * @return array{key: string, label: string, items: list<array{title: string, subtitle: string|null, href: string}>}
     */
    private function emptySection(string $key, string $label): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'items' => [],
        ];
    }
}
