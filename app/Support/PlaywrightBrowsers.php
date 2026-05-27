<?php

namespace App\Support;

class PlaywrightBrowsers
{
    public static function storageDirectory(): string
    {
        return storage_path('app/playwright-browsers');
    }

    /**
     * Resolved PLAYWRIGHT_BROWSERS_PATH for Node subprocesses, or null when unknown.
     */
    public static function resolve(): ?string
    {
        $configured = config('scanner.playwright_browsers_path');

        if ($configured !== null && $configured !== '') {
            if ($configured === '0' && !is_dir(base_path('scripts/node_modules/.cache/ms-playwright'))) {
                return self::detectBundledOrStorage() ?? $configured;
            }

            return (string) $configured;
        }

        return self::detectBundledOrStorage();
    }

    private static function detectBundledOrStorage(): ?string
    {
        if (self::hasInstalledBrowsers(self::storageDirectory())) {
            return self::storageDirectory();
        }

        if (is_dir(base_path('scripts/node_modules/.cache/ms-playwright'))) {
            return '0';
        }

        return null;
    }

    public static function hasInstalledBrowsers(string $directory): bool
    {
        if (!is_dir($directory)) {
            return false;
        }

        $entries = glob($directory.'/*', GLOB_ONLYDIR);

        return is_array($entries) && $entries !== [];
    }
}
