<?php

namespace App\Support;

use Illuminate\Support\Str;

final class NicheQueryResolver
{
    public static function forLabel(string $label): string
    {
        return self::forLabelWithFallback($label);
    }

    public static function forLabelWithFallback(string $label, ?string $fallback = null): string
    {
        foreach (config('niches.niches', []) as $entry) {
            if (($entry['label'] ?? null) === $label) {
                return (string) $entry['query'];
            }
        }

        if ($fallback !== null && $fallback !== '') {
            return $fallback;
        }

        return Str::lower($label);
    }
}
