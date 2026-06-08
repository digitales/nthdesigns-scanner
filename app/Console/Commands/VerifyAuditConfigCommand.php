<?php

namespace App\Console\Commands;

use App\Services\ApiHealthService;
use App\Services\BrowserServiceClient;
use App\Support\PlaywrightScripts;
use App\Support\ScannerConfig;
use Illuminate\Console\Command;

class VerifyAuditConfigCommand extends Command
{
    protected $signature = 'scanner:verify-audit-config';

    protected $description = 'Verify audit driver configuration for Laravel Cloud (Fly HTTP) vs local Playwright';

    public function handle(ApiHealthService $health, BrowserServiceClient $browserService): int
    {
        $auditDriver = (string) config('scanner.audit_driver');
        $serviceUrl = config('scanner.audit_service_url');
        $hasToken = config('scanner.audit_service_token') !== null
            && config('scanner.audit_service_token') !== '';
        $depsInstalled = PlaywrightScripts::dependenciesInstalled();

        $this->line('Audit configuration');
        $this->table(
            ['Setting', 'Value'],
            [
                ['audit_driver', $auditDriver],
                ['AUDIT_SERVICE_URL', $serviceUrl ?: '(not set)'],
                ['AUDIT_SERVICE_TOKEN', $hasToken ? '(set)' : '(not set)'],
                ['scripts/node_modules/playwright', $depsInstalled ? 'installed' : 'MISSING'],
                ['base_path', base_path()],
            ],
        );

        if ($auditDriver === 'http') {
            $check = $browserService->healthCheck();
            $this->line($check['ok'] ? '<info>browser_service: '.$check['message'].'</info>' : '<error>browser_service: '.$check['message'].'</error>');

            if (! $check['ok']) {
                $this->newLine();
                $this->warn('Fix Fly browser service (see docs/deployment/laravel-cloud.md §10).');

                return self::FAILURE;
            }

            $this->newLine();
            $this->info('Audit routing looks correct for Laravel Cloud (HTTP → Fly).');

            return self::SUCCESS;
        }

        if ($auditDriver === 'playwright' && ! $depsInstalled) {
            $this->newLine();
            $this->error('Local Playwright driver is active but scripts npm packages are missing.');
            $this->line(PlaywrightScripts::missingDependenciesMessage());
            $this->newLine();
            $this->warn('Laravel Cloud production fix — set on app + auditing worker, then redeploy:');
            $this->line('  AUDIT_SERVICE_URL='.ScannerConfig::PRODUCTION_BROWSER_SERVICE_URL.' (optional on production — defaulted when APP_ENV=production)');
            $this->line('  AUDIT_SERVICE_TOKEN=<same value as BROWSER_SERVICE_TOKEN on Fly>');

            return self::FAILURE;
        }

        if ($auditDriver === 'playwright') {
            $playwright = $health->checkAll()['playwright'] ?? ['ok' => false, 'message' => 'unknown'];
            $this->line($playwright['ok'] ? '<info>playwright: '.$playwright['message'].'</info>' : '<error>playwright: '.$playwright['message'].'</error>');

            $this->newLine();
            $this->info('Local Playwright path is configured.');

            return $playwright['ok'] ? self::SUCCESS : self::FAILURE;
        }

        $this->newLine();
        $this->info('Audit driver is "'.$auditDriver.'" — no Playwright or HTTP checks required.');

        return self::SUCCESS;
    }
}
