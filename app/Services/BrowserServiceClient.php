<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class BrowserServiceClient
{
    /**
     * @return array<string, mixed>
     */
    public function fetchAudit(string $url): array
    {
        $response = $this->request()
            ->timeout(config('scanner.audit_timeout'))
            ->post($this->endpoint('/audit'), ['url' => $url]);

        if (!$response->successful()) {
            throw new \RuntimeException(
                'Audit service failed: '.trim($response->body())
            );
        }

        $payload = $response->json();

        if (!is_array($payload)) {
            throw new \RuntimeException('Audit service returned invalid JSON');
        }

        if (!empty($payload['error'])) {
            throw new \RuntimeException('Audit service error: '.$payload['error']);
        }

        return $payload;
    }

    /**
     * Write violation PNGs from base64 fields and strip them from the payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function materializeViolationScreenshots(array $payload, string $localDir): array
    {
        $shots = $payload['violation_screenshots'] ?? [];

        if (!is_array($shots)) {
            return $payload;
        }

        $materialized = [];

        foreach ($shots as $shot) {
            if (!is_array($shot)) {
                continue;
            }

            $file = $shot['file'] ?? null;
            $base64 = $shot['content_base64'] ?? null;

            if (is_string($file) && is_string($base64) && $base64 !== '') {
                $decoded = base64_decode($base64, true);

                if ($decoded !== false) {
                    $path = rtrim($localDir, '/').'/'.basename($file);
                    file_put_contents($path, $decoded);
                }
            }

            unset($shot['content_base64']);
            $materialized[] = $shot;
        }

        $payload['violation_screenshots'] = $materialized;

        return $payload;
    }

    public function captureDesktop(string $url, string $localDir): string
    {
        $response = $this->request()
            ->timeout(90)
            ->post($this->endpoint('/screenshot'), ['url' => $url]);

        if (!$response->successful()) {
            throw new \RuntimeException(
                'Screenshot service failed: '.trim($response->body())
            );
        }

        $payload = $response->json();

        if (!is_array($payload) || !empty($payload['error'])) {
            throw new \RuntimeException($payload['error'] ?? 'Screenshot service returned invalid JSON');
        }

        $base64 = $payload['content_base64'] ?? null;
        $filename = basename((string) ($payload['desktop'] ?? 'desktop.png'));

        if (!is_string($base64) || $base64 === '') {
            throw new \RuntimeException('Screenshot service did not return image data');
        }

        $decoded = base64_decode($base64, true);

        if ($decoded === false) {
            throw new \RuntimeException('Screenshot service returned invalid base64');
        }

        if (!is_dir($localDir)) {
            mkdir($localDir, 0755, true);
        }

        $absolutePath = rtrim($localDir, '/').'/'.$filename;
        file_put_contents($absolutePath, $decoded);

        return $absolutePath;
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function healthCheck(): array
    {
        $baseUrl = $this->baseUrl();

        if ($baseUrl === '') {
            return ['ok' => false, 'message' => 'AUDIT_SERVICE_URL is not set'];
        }

        try {
            $response = $this->request()->timeout(10)->get($this->endpoint('/health'));

            if ($response->successful() && ($response->json('ok') === true || $response->json('status') === 'ok')) {
                return ['ok' => true, 'message' => 'Browser service reachable'];
            }

            return ['ok' => false, 'message' => 'Browser service health check failed ('.$response->status().')'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    private function request(): PendingRequest
    {
        $request = Http::acceptJson()->asJson();

        $token = config('scanner.audit_service_token');

        if ($token) {
            $request = $request->withToken($token);
        }

        return $request;
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('scanner.audit_service_url'), '/');
    }

    private function endpoint(string $path): string
    {
        return $this->baseUrl().$path;
    }
}
