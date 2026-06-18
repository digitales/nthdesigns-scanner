<?php

namespace App\Services;

use App\Models\Prospect;
use Illuminate\Support\Collection;

class ProspectValidationSignalMatcher
{
    /**
     * @param  Collection<int, array{pattern: string, source: string, signal_id: int|null, label: string|null}>  $signals
     * @param  list<string>  $matchFields
     * @return array{pattern: string, field: string, source: string, signal_id: int|null}|null
     */
    public function match(Prospect $prospect, Collection $signals, array $matchFields): ?array
    {
        $fieldValues = $this->fieldValues($prospect, $matchFields);

        foreach ($signals as $signal) {
            foreach ($fieldValues as $field => $values) {
                foreach ($values as $value) {
                    if ($value !== '' && str_contains($value, $signal['pattern'])) {
                        return [
                            'pattern' => $signal['pattern'],
                            'field' => $field,
                            'source' => $signal['source'],
                            'signal_id' => $signal['signal_id'],
                        ];
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $matchFields
     * @return array<string, list<string>>
     */
    private function fieldValues(Prospect $prospect, array $matchFields): array
    {
        $values = [];

        foreach ($matchFields as $field) {
            $values[$field] = match ($field) {
                'qualification_flags' => array_map(
                    'strtolower',
                    array_map('strval', $prospect->qualification_flags ?? []),
                ),
                'business_name' => $this->singleValue($prospect->business_name),
                'website_url' => $this->singleValue($this->normaliseWebsiteUrl($prospect->website_url)),
                'qualification_summary' => $this->singleValue($prospect->qualification_summary),
                default => [],
            };
        }

        return $values;
    }

    /**
     * @return list<string>
     */
    private function singleValue(?string $value): array
    {
        $normalised = strtolower(trim((string) $value));

        return $normalised === '' ? [] : [$normalised];
    }

    private function normaliseWebsiteUrl(?string $url): string
    {
        if (blank($url)) {
            return '';
        }

        return (string) preg_replace('#^https?://#i', '', trim($url));
    }
}
