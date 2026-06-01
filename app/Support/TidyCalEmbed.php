<?php

namespace App\Support;

class TidyCalEmbed
{
    public static function pathFromUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        $parts = parse_url($url);

        if ($parts === false) {
            return null;
        }

        $host = strtolower($parts['host'] ?? '');

        if (! in_array($host, ['tidycal.com', 'www.tidycal.com'], true)) {
            return null;
        }

        $path = trim($parts['path'] ?? '', '/');

        return $path !== '' ? $path : null;
    }

    public static function isEmbeddable(?string $url): bool
    {
        return self::pathFromUrl($url) !== null;
    }

    /**
     * @param  array<string, string>  $query
     */
    public static function bookPageUrl(?string $bookingUrl, array $query = []): ?string
    {
        if ($bookingUrl === null || $bookingUrl === '') {
            return null;
        }

        if (! self::isEmbeddable($bookingUrl)) {
            return $bookingUrl;
        }

        return route('book.index', $query);
    }
}
