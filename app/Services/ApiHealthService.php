<?php

namespace App\Services;

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

        return ['ok' => true, 'message' => 'API key configured'];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function checkAnthropic(): array
    {
        $key = config('services.anthropic.key', '');

        if ($key === '') {
            return ['ok' => false, 'message' => 'ANTHROPIC_API_KEY is not set'];
        }

        return ['ok' => true, 'message' => 'API key configured'];
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
}
