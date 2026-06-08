<?php

namespace App\Support;

final class PlaywrightScripts
{
    public static function packageDirectory(): string
    {
        return base_path('scripts/node_modules/playwright');
    }

    public static function dependenciesInstalled(): bool
    {
        return is_dir(self::packageDirectory());
    }

    public static function missingDependenciesMessage(): string
    {
        return 'Playwright npm packages are not installed (scripts/node_modules/playwright missing). '
            .'Local dev: run `cd scripts && npm ci && npx playwright install chromium`. '
            .'Laravel Cloud production: set AUDIT_SERVICE_URL and AUDIT_SERVICE_TOKEN '
            .'(see docs/deployment/laravel-cloud.md) — do not run Playwright on Cloud workers.';
    }
}
