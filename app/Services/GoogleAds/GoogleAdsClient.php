<?php

namespace App\Services\GoogleAds;

use App\Exceptions\GoogleAdsApiException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class GoogleAdsClient
{
    public function __construct(
        private GoogleAdsAccessTokenProvider $tokens,
    ) {}

    public function isConfigured(): bool
    {
        return config('google_ads.enabled')
            && $this->tokens->isConfigured()
            && filled(config('google_ads.developer_token'))
            && filled(config('google_ads.customer_id'));
    }

    /**
     * @return array<string, mixed>
     */
    public function post(string $path, array $body): array
    {
        $response = $this->request()->post($this->url($path), $body);

        if (! $response->successful()) {
            throw new GoogleAdsApiException(
                'Google Ads API request failed.',
                $response->status(),
                $response->json(),
            );
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    private function request(): PendingRequest
    {
        $headers = [
            'Authorization' => 'Bearer '.$this->tokens->accessToken(),
            'developer-token' => (string) config('google_ads.developer_token'),
        ];

        $loginCustomerId = config('google_ads.login_customer_id');

        if ($loginCustomerId !== '') {
            $headers['login-customer-id'] = $loginCustomerId;
        }

        return Http::withHeaders($headers)
            ->acceptJson()
            ->timeout(30);
    }

    private function url(string $path): string
    {
        $version = config('google_ads.api_version');
        $path = ltrim($path, '/');

        return "https://googleads.googleapis.com/{$version}/{$path}";
    }
}
