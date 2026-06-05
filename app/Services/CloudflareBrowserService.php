<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class CloudflareBrowserService
{
    /**
     * Capture a desktop viewport screenshot and write PNG bytes to $outputPath.
     */
    public function captureScreenshot(string $url, string $outputPath): void
    {
        $token = config('services.cloudflare.api_token', '');
        $accountId = config('services.cloudflare.account_id', '');

        if ($token === '' || $accountId === '') {
            throw new \RuntimeException('CLOUDFLARE_API_TOKEN and CLOUDFLARE_ACCOUNT_ID are required for Cloudflare screenshots');
        }

        $response = Http::withToken($token)
            ->timeout(90)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post(
                "https://api.cloudflare.com/client/v4/accounts/{$accountId}/browser-rendering/screenshot",
                [
                    'url' => $url,
                    'viewport' => [
                        'width' => 1280,
                        'height' => 800,
                    ],
                    'gotoOptions' => [
                        'waitUntil' => 'networkidle0',
                        'timeout' => 45000,
                    ],
                ],
            );

        if (! $response->successful()) {
            $message = $response->json('errors.0.message') ?? $response->body();

            throw new \RuntimeException('Cloudflare screenshot failed: '.$message);
        }

        $written = file_put_contents($outputPath, $response->body());

        if ($written === false) {
            throw new \RuntimeException('Failed to write Cloudflare screenshot to disk');
        }
    }

    public function isConfigured(): bool
    {
        return config('services.cloudflare.api_token', '') !== ''
            && config('services.cloudflare.account_id', '') !== '';
    }
}
