<?php

namespace App\Services;

use App\Models\Prospect;
use Illuminate\Support\LazyCollection;

class ProspectValidationRevalidationService
{
    /**
     * @return LazyCollection<int, Prospect>
     */
    public function matchingProspects(string $pattern, ?string $oldPattern = null): LazyCollection
    {
        $patterns = collect([$pattern, $oldPattern])
            ->filter()
            ->map(fn ($value) => strtolower(trim($value)))
            ->unique()
            ->values()
            ->all();

        if ($patterns === []) {
            return LazyCollection::empty();
        }

        return Prospect::query()
            ->whereNotNull('validator_ran_at')
            ->whereNull('validator_override_status')
            ->where(function ($query) use ($patterns): void {
                foreach ($patterns as $index => $signalPattern) {
                    $like = '%'.$signalPattern.'%';
                    $flagLike = '%franchise_signal:'.$signalPattern.':%';
                    $method = $index === 0 ? 'where' : 'orWhere';

                    $query->{$method}(function ($inner) use ($like, $flagLike): void {
                        $inner->whereRaw('LOWER(business_name) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(website_url) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(qualification_summary) LIKE ?', [$like])
                            ->orWhere('validator_flags', 'like', $flagLike);
                    });
                }
            })
            ->lazyById(100);
    }
}
