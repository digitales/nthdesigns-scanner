<?php

namespace App\Services\Browser;

use Illuminate\Http\Client\ConnectionException;

class BrowserAuditGateway
{
    public function __construct(private BrowserHttpTransport $http) {}

    /**
     * @return array<string, mixed>
     */
    public function fetch(string $url): array
    {
        try {
            $response = $this->http->request()
                ->timeout(config('scanner.audit_timeout'))
                ->post($this->http->endpoint('/audit'), ['url' => $url]);
        } catch (ConnectionException $e) {
            return $this->unreachablePayload($url, $e->getMessage());
        }

        if (! $response->successful()) {
            $payload = $this->parseFailedResponse($response->body());

            if ($payload !== null) {
                return $payload;
            }

            throw new \RuntimeException(
                'Audit service failed: '.trim($response->body())
            );
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new \RuntimeException('Audit service returned invalid JSON');
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseFailedResponse(string $body): ?array
    {
        $decoded = json_decode($body, true);

        if (! is_array($decoded)) {
            return null;
        }

        $nested = $decoded['error'] ?? null;

        if (is_string($nested)) {
            $payload = json_decode($nested, true);

            if (is_array($payload) && (isset($payload['url']) || isset($payload['violations']))) {
                return $payload;
            }
        }

        if (isset($decoded['url']) || array_key_exists('error', $decoded)) {
            return $decoded;
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function unreachablePayload(string $url, string $message): array
    {
        return [
            'url' => $url,
            'error' => $message,
            'violations' => [],
            'pass_count' => 0,
            'incomplete_count' => 0,
            'violation_screenshots' => [],
            'lighthouse' => null,
        ];
    }
}
