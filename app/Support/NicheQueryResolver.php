<?php

namespace App\Support;

use Illuminate\Support\Str;

final class NicheQueryResolver
{
    public static function forLabel(string $label): string
    {
        foreach (config('niches.niches', []) as $entry) {
            if (($entry['label'] ?? null) === $label) {
                return (string) $entry['query'];
            }
        }

        return Str::lower($label);
    }
}
