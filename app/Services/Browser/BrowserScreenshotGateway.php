<?php

namespace App\Services\Browser;

use Illuminate\Http\Client\ConnectionException;

class BrowserScreenshotGateway
{
    public function __construct(private BrowserHttpTransport $http) {}

    public function captureDesktop(string $url, string $localDir): string
    {
        try {
            $response = $this->http->request()
                ->timeout(config('scanner.screenshot_timeout'))
                ->post($this->http->endpoint('/screenshot'), ['url' => $url]);
        } catch (ConnectionException $e) {
            throw new \RuntimeException('Screenshot service unreachable: '.$e->getMessage(), 0, $e);
        }

        if (! $response->successful()) {
            $payload = $this->parseFailedResponse($response->body());

            if ($payload !== null && ! empty($payload['error'])) {
                throw new \RuntimeException((string) $payload['error']);
            }

            throw new \RuntimeException(
                'Screenshot service failed: '.trim($response->body())
            );
        }

        $payload = $response->json();

        if (! is_array($payload) || ! empty($payload['error'])) {
            throw new \RuntimeException($payload['error'] ?? 'Screenshot service returned invalid JSON');
        }

        $base64 = $payload['content_base64'] ?? null;
        $filename = basename((string) ($payload['desktop'] ?? 'desktop.png'));

        if (! is_string($base64) || $base64 === '') {
            throw new \RuntimeException('Screenshot service did not return image data');
        }

        $decoded = base64_decode($base64, true);

        if ($decoded === false) {
            throw new \RuntimeException('Screenshot service returned invalid base64');
        }

        if (! is_dir($localDir)) {
            mkdir($localDir, 0755, true);
        }

        $absolutePath = rtrim($localDir, '/').'/'.$filename;
        file_put_contents($absolutePath, $decoded);

        return $absolutePath;
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

            if (is_array($payload) && array_key_exists('error', $payload)) {
                return $payload;
            }
        }

        if (array_key_exists('error', $decoded)) {
            return $decoded;
        }

        return null;
    }
}
