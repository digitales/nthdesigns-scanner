<?php

namespace App\Support;

final class WeakWebsiteHostChecker
{
    public function isWeak(string $uri): bool
    {
        $host = strtolower((string) parse_url($uri, PHP_URL_HOST));

        if ($host === '') {
            return false;
        }

        $needles = [
            'facebook.com',
            'fb.com',
            'instagram.com',
            'linktr.ee',
            'tiktok.com',
            'twitter.com',
            'x.com',
            'yelp.',
            'wixsite.com',
            'square.site',
            'godaddysites.com',
            'google.site',
            'sites.google.com',
        ];

        foreach ($needles as $needle) {
            if (str_contains($host, $needle)) {
                return true;
            }
        }

        return false;
    }
}
