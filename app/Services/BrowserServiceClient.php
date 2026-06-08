<?php

namespace App\Services;

use App\Services\Browser\BrowserAuditGateway;
use App\Services\Browser\BrowserCmsGateway;
use App\Services\Browser\BrowserHttpTransport;
use App\Services\Browser\BrowserScreenshotGateway;
use App\Services\Browser\ViolationScreenshotMaterializer;

class BrowserServiceClient
{
    public function __construct(
        private BrowserAuditGateway $audit,
        private BrowserCmsGateway $cms,
        private BrowserScreenshotGateway $screenshot,
        private BrowserHttpTransport $http,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function fetchAudit(string $url): array
    {
        return $this->audit->fetch($url);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function materializeViolationScreenshots(array $payload, string $localDir): array
    {
        return (new ViolationScreenshotMaterializer)->materialize($payload, $localDir);
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchCmsDetection(string $url): array
    {
        return $this->cms->fetch($url);
    }

    public function captureDesktop(string $url, string $localDir): string
    {
        return $this->screenshot->captureDesktop($url, $localDir);
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function healthCheck(): array
    {
        $baseUrl = $this->http->baseUrl();

        if ($baseUrl === '') {
            return ['ok' => false, 'message' => 'AUDIT_SERVICE_URL is not set'];
        }

        try {
            $response = $this->http->request()->timeout(10)->get($this->http->endpoint('/health'));

            if ($response->successful() && ($response->json('ok') === true || $response->json('status') === 'ok')) {
                return ['ok' => true, 'message' => 'Browser service reachable'];
            }

            return ['ok' => false, 'message' => 'Browser service health check failed ('.$response->status().')'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }
}
