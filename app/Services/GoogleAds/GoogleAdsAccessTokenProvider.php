<?php

namespace App\Services\GoogleAds;

use App\Exceptions\GoogleAdsApiException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GoogleAdsAccessTokenProvider
{
    public function isConfigured(): bool
    {
        $oauth = config('google_ads.oauth');

        return filled($oauth['client_id'])
            && filled($oauth['client_secret'])
            && filled($oauth['refresh_token']);
    }

    public function accessToken(): string
    {
        if (! $this->isConfigured()) {
            throw new GoogleAdsApiException('Google Ads OAuth is not configured.');
        }

        return Cache::remember('google_ads.access_token', now()->addMinutes(55), function (): string {
            $oauth = config('google_ads.oauth');

            $response = Http::asForm()
                ->timeout(15)
                ->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'refresh_token',
                    'client_id' => $oauth['client_id'],
                    'client_secret' => $oauth['client_secret'],
                    'refresh_token' => $oauth['refresh_token'],
                ]);

            if (! $response->successful()) {
                throw new GoogleAdsApiException(
                    'Google Ads OAuth token refresh failed.',
                    $response->status(),
                    $response->json(),
                );
            }

            $token = $response->json('access_token');

            if (! is_string($token) || $token === '') {
                throw new GoogleAdsApiException('Google Ads OAuth response missing access_token.');
            }

            return $token;
        });
    }

    public function flush(): void
    {
        Cache::forget('google_ads.access_token');
    }
}
