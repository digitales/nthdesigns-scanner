<?php

namespace App\Services\Browser;

use Illuminate\Http\Client\ConnectionException;

class BrowserCmsGateway
{
    public function __construct(private BrowserHttpTransport $http) {}

    /**
     * @return array<string, mixed>
     */
    public function fetch(string $url): array
    {
        try {
            $response = $this->http->request()
                ->timeout((int) config('scanner.cms_detect_timeout', 90))
                ->post($this->http->endpoint('/detect-cms'), ['url' => $url]);
        } catch (ConnectionException $e) {
            return [
                'platform' => 'unknown',
                'version' => null,
                'confidence' => 'low',
                'signals' => [
                    ['id' => 'fetch_failed', 'matched' => true, 'detail' => $e->getMessage()],
                ],
                'detected_at' => now()->toIso8601String(),
                'url' => $url,
            ];
        }

        if (! $response->successful()) {
            $body = trim($response->body());

            if ($response->status() === 404) {
                throw new \RuntimeException(
                    'CMS detect endpoint not found on browser service — redeploy Fly with the latest scripts/browser-service (POST /detect-cms). Response: '.$body
                );
            }

            throw new \RuntimeException(
                'CMS detect service failed: '.$body
            );
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new \RuntimeException('CMS detect service returned invalid JSON');
        }

        return $payload;
    }
}
