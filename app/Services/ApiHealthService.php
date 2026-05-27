<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class ApiHealthService
{
    /**
     * @return array<string, array{ok: bool, message: string}>
     */
    public function checkAll(): array
    {
        $checks = [
            'google_places' => $this->checkGooglePlaces(),
            'anthropic'     => $this->checkAnthropic(),
            'storage'       => $this->checkStorage(),
        ];

        if (config('scanner.screenshot_driver') === 'cloudflare') {
            $checks['cloudflare'] = $this->checkCloudflare();
        }

        if (config('scanner.audit_driver') === 'http') {
            $checks['audit_service'] = $this->checkAuditService();
        }

        if ($this->usesLocalNode()) {
            $checks['node'] = $this->checkNode();
            $checks['playwright'] = $this->checkPlaywright();
        }

        return $checks;
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function checkGooglePlaces(): array
    {
        $key = config('services.google_places.key', '');

        if ($key === '') {
            return ['ok' => false, 'message' => 'GOOGLE_PLACES_API_KEY is not set'];
        }

        $response = Http::withHeaders([
            'Content-Type'     => 'application/json',
            'X-Goog-Api-Key'   => $key,
            'X-Goog-FieldMask' => 'places.id',
        ])->post('https://places.googleapis.com/v1/places:searchText', [
            'textQuery'      => 'coffee shop in London, GB',
            'maxResultCount' => 1,
            'regionCode'     => 'gb',
        ]);

        if ($response->successful()) {
            return ['ok' => true, 'message' => 'Places API (New) responded OK'];
        }

        $body = $response->json() ?? [];
        $reason = $body['error']['details'][0]['reason'] ?? null;

        if ($reason === 'API_KEY_HTTP_REFERRER_BLOCKED') {
            return [
                'ok'      => false,
                'message' => 'API key uses HTTP referrer restrictions — server requests have no referer. In Google Cloud, use Application restriction "IP addresses" (production) or "None" (local dev), not "Websites".',
            ];
        }

        $message = $body['error']['message'] ?? $response->body();

        return ['ok' => false, 'message' => "Places API error ({$response->status()}): {$message}"];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function checkAnthropic(): array
    {
        $key = config('services.openrouter.key', '');

        if ($key === '') {
            return ['ok' => false, 'message' => 'OPENROUTER_API_KEY is not set'];
        }

        $model = config('services.openrouter.model', 'anthropic/claude-sonnet-4');

        return ['ok' => true, 'message' => "OpenRouter configured ({$model})"];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function checkStorage(): array
    {
        $diskName = config('scanner.reports_disk', 'public');

        try {
            $disk = Storage::disk($diskName);
            $path = '.health-check/'.uniqid('', true).'.txt';
            $disk->put($path, 'ok');
            $disk->delete($path);

            return ['ok' => true, 'message' => "Disk \"{$diskName}\" is writable"];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function checkCloudflare(): array
    {
        $token = config('services.cloudflare.api_token', '');
        $accountId = config('services.cloudflare.account_id', '');

        if ($token === '' || $accountId === '') {
            return ['ok' => false, 'message' => 'CLOUDFLARE_API_TOKEN and CLOUDFLARE_ACCOUNT_ID are required'];
        }

        return ['ok' => true, 'message' => 'Cloudflare Browser Rendering configured'];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function checkAuditService(): array
    {
        $url = config('scanner.audit_service_url', '');

        if ($url === '') {
            return ['ok' => false, 'message' => 'AUDIT_SERVICE_URL is not set'];
        }

        return ['ok' => true, 'message' => 'Audit service URL configured'];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function checkNode(): array
    {
        $binary = (string) config('scanner.node_binary');
        $probe = Process::timeout(5)->run([$binary, '--version']);

        if ($probe->successful()) {
            return [
                'ok'      => true,
                'message' => 'Node '.trim($probe->output()).' ('.$binary.')',
            ];
        }

        $which = trim(Process::run(['which', 'node'])->output());

        return [
            'ok'      => false,
            'message' => 'NODE_BINARY ('.$binary.') is missing or not executable'.
                ($which !== '' ? " — try NODE_BINARY={$which}" : ' — set NODE_BINARY=node in .env'),
        ];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function checkPlaywright(): array
    {
        $browsersPath = config('scanner.playwright_browsers_path');

        if ($browsersPath === null || $browsersPath === '') {
            return [
                'ok'      => true,
                'message' => 'Using default Playwright browser cache',
            ];
        }

        if ($browsersPath !== '0') {
            if (!is_dir($browsersPath)) {
                return [
                    'ok'      => false,
                    'message' => 'PLAYWRIGHT_BROWSERS_PATH directory not found: '.$browsersPath,
                ];
            }

            return ['ok' => true, 'message' => 'Chromium at '.$browsersPath];
        }

        $bundled = base_path('scripts/node_modules/.cache/ms-playwright');

        if (!is_dir($bundled)) {
            return [
                'ok'      => false,
                'message' => 'Bundled Chromium missing — run `PLAYWRIGHT_BROWSERS_PATH=0 npx playwright install chromium` in scripts/ during build',
            ];
        }

        return ['ok' => true, 'message' => 'Bundled Chromium (PLAYWRIGHT_BROWSERS_PATH=0)'];
    }

    private function usesLocalNode(): bool
    {
        return config('scanner.audit_driver') === 'playwright'
            || config('scanner.screenshot_driver') === 'playwright';
    }
}
