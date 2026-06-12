<?php

namespace App\Services;

use App\Support\ReachabilityResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class WebsiteReachabilityService
{
    public function check(string $url): ReachabilityResult
    {
        if (! config('scanner.site_preflight_enabled', true)) {
            return ReachabilityResult::ok();
        }

        $retries = max(0, (int) config('scanner.site_preflight_retries', 2));
        $attempts = 1 + $retries;
        $lastResult = ReachabilityResult::failed('Site unreachable', permanent: true);

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $lastResult = $this->probeOnce($url);

            if ($lastResult->isReachable() || $lastResult->permanent) {
                return $lastResult;
            }

            if ($attempt < $attempts) {
                sleep(2);
            }
        }

        return $lastResult;
    }

    private function probeOnce(string $url): ReachabilityResult
    {
        try {
            $response = Http::withOptions([
                'allow_redirects' => ['max' => 5],
            ])
                ->connectTimeout((int) config('scanner.site_preflight_connect_timeout', 5))
                ->timeout((int) config('scanner.site_preflight_timeout', 10))
                ->withUserAgent((string) config('scanner.site_preflight_user_agent', 'nthdesigns-scanner-preflight/1.0'))
                ->get($url);

            if ($response->successful() || $response->redirect()) {
                return ReachabilityResult::ok();
            }

            if ($response->serverError()) {
                return ReachabilityResult::failed(
                    'HTTP '.$response->status().' from '.$url,
                    permanent: false,
                );
            }

            return ReachabilityResult::ok();
        } catch (ConnectionException $e) {
            return $this->classifyMessage($e->getMessage(), permanentByDefault: true);
        } catch (RequestException $e) {
            if ($e->response !== null && $e->response->serverError()) {
                return ReachabilityResult::failed(
                    'HTTP '.$e->response->status().' from '.$url,
                    permanent: false,
                );
            }

            return $this->classifyMessage($e->getMessage(), permanentByDefault: false);
        }
    }

    private function classifyMessage(string $message, bool $permanentByDefault): ReachabilityResult
    {
        $normalized = Str::lower($message);

        if ($this->isPermanentMessage($normalized)) {
            return ReachabilityResult::failed(trim($message) ?: 'Site unreachable', permanent: true);
        }

        if ($this->isTransientMessage($normalized)) {
            return ReachabilityResult::failed(trim($message) ?: 'Site unreachable', permanent: false);
        }

        return ReachabilityResult::failed(trim($message) ?: 'Site unreachable', permanent: $permanentByDefault);
    }

    private function isPermanentMessage(string $message): bool
    {
        foreach ([
            'could not resolve host',
            'name or service not known',
            'nodename nor servname provided',
            'getaddrinfo failed',
            'connection refused',
            'failed to connect',
            'no route to host',
            'err_name_not_resolved',
            'ssl: no alternative certificate subject name',
            'certificate subject name',
            'curl error 6',
            'curl error 7',
        ] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function isTransientMessage(string $message): bool
    {
        foreach ([
            'timed out',
            'timeout',
            'connection reset',
            'curl error 28',
            '502',
            '503',
            '504',
        ] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }
}
