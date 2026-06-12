<?php

namespace App\Services\Outreach;

use App\Models\OutreachSelection;
use App\Models\Prospect;
use Illuminate\Support\Collection;

class CpcBenchmarkResolver
{
    /**
     * @param  Collection<int, OutreachSelection>  $selections
     * @return array{value: string, mixed: bool, from_search: bool}
     */
    public function formDefaults(Collection $selections): array
    {
        $values = $selections
            ->map(fn (OutreachSelection $selection) => $selection->prospect->search?->cpc_benchmark)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value) => number_format((float) $value, 2, '.', ''))
            ->unique()
            ->values();

        if ($values->isEmpty()) {
            return [
                'value' => '',
                'mixed' => false,
                'from_search' => false,
            ];
        }

        if ($values->count() > 1) {
            return [
                'value' => '',
                'mixed' => true,
                'from_search' => true,
            ];
        }

        return [
            'value' => $values->first(),
            'mixed' => false,
            'from_search' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{cpc_benchmark?: float, cpc_source?: string}
     */
    public function resolveForProspect(Prospect $prospect, array $options): array
    {
        if (array_key_exists('cpc_benchmark', $options) && $options['cpc_benchmark'] !== null && $options['cpc_benchmark'] !== '') {
            return [
                'cpc_benchmark' => (float) $options['cpc_benchmark'],
                'cpc_source' => (string) ($options['cpc_source'] ?? 'outreach_override'),
            ];
        }

        $search = $prospect->search;

        if ($search?->cpc_benchmark === null) {
            return [];
        }

        return [
            'cpc_benchmark' => (float) $search->cpc_benchmark,
            'cpc_source' => $search->cpc_source ?? 'search',
        ];
    }
}
