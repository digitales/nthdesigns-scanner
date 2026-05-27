<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

class AuditRunnerService
{
    public function shouldSkip(): bool
    {
        return config('scanner.audit_driver') === 'skip';
    }

    /**
     * @return array<string, mixed>
     */
    public function run(string $url, string $screenshotDir): array
    {
        return match (config('scanner.audit_driver')) {
            'http' => $this->runHttp($url),
            default => $this->runPlaywright($url, $screenshotDir),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function runPlaywright(string $url, string $screenshotDir): array
    {
        $this->assertNodeBinaryAvailable();

        $result = Process::timeout(config('scanner.audit_timeout'))
            ->env([
                'LIGHTHOUSE_BINARY' => config('scanner.lighthouse_binary'),
            ])
            ->run([
                config('scanner.node_binary'),
                config('scanner.audit_script_path'),
                $url,
                $screenshotDir,
            ]);

        if (!$result->successful()) {
            throw new \RuntimeException(
                'Audit script failed: '.trim($result->errorOutput() ?: $result->output())
            );
        }

        $payload = json_decode($result->output(), true);

        if (!is_array($payload)) {
            throw new \RuntimeException('Audit script returned invalid JSON');
        }

        if (!empty($payload['error'])) {
            throw new \RuntimeException('Audit script error: '.$payload['error']);
        }

        return $payload;
    }

    private function assertNodeBinaryAvailable(): void
    {
        $binary = (string) config('scanner.node_binary');

        $probe = Process::timeout(5)->run([$binary, '--version']);

        if ($probe->successful()) {
            return;
        }

        $stderr = trim($probe->errorOutput() ?: $probe->output());

        throw new \RuntimeException(
            'Node.js is not available at NODE_BINARY ('.$binary.'). '.
            ($stderr !== '' ? $stderr.'. ' : '').
            'Set NODE_BINARY=node or the path from `which node` in .env, then restart queue workers.'
        );
    }

    /**
     * POST {AUDIT_SERVICE_URL}/audit — response body must match audit.js JSON output.
     *
     * @return array<string, mixed>
     */
    private function runHttp(string $url): array
    {
        $baseUrl = rtrim((string) config('scanner.audit_service_url'), '/');

        if ($baseUrl === '') {
            throw new \RuntimeException('AUDIT_SERVICE_URL is required when audit driver is http');
        }

        $request = Http::timeout(config('scanner.audit_timeout'))
            ->acceptJson()
            ->asJson();

        $token = config('scanner.audit_service_token');

        if ($token) {
            $request = $request->withToken($token);
        }

        $response = $request->post("{$baseUrl}/audit", ['url' => $url]);

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
}
