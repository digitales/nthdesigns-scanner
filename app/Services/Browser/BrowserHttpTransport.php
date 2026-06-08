<?php

namespace App\Services\Browser;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class BrowserHttpTransport
{
    public function request(): PendingRequest
    {
        $request = Http::acceptJson()->asJson();

        $token = config('scanner.audit_service_token');

        if ($token) {
            $request = $request->withToken($token);
        }

        return $request;
    }

    public function baseUrl(): string
    {
        return rtrim((string) config('scanner.audit_service_url'), '/');
    }

    public function endpoint(string $path): string
    {
        return $this->baseUrl().$path;
    }
}
