<?php

namespace App\Support;

use InvalidArgumentException;

final class WebsiteUrlNormalizer
{
    public function normalize(string $input): string
    {
        $trimmed = trim($input);

        if ($trimmed === '') {
            throw new InvalidArgumentException('URL is required.');
        }

        if (! preg_match('#^https?://#i', $trimmed)) {
            $trimmed = 'https://'.$trimmed;
        }

        $parts = parse_url($trimmed);

        if ($parts === false || empty($parts['host'])) {
            throw new InvalidArgumentException('Invalid URL.');
        }

        $scheme = strtolower($parts['scheme'] ?? '');

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('URL must use http or https.');
        }

        $host = strtolower($parts['host']);

        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        $path = $parts['path'] ?? '';

        if ($path === '/') {
            $path = '';
        }

        $path = rtrim($path, '/');

        return $scheme.'://'.$host.$path;
    }

    public function host(string $url): string
    {
        $normalized = $this->normalize($url);
        $host = parse_url($normalized, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            throw new InvalidArgumentException('Invalid URL host.');
        }

        $host = strtolower($host);

        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        return $host;
    }

    public function displayNameFromUrl(string $url): string
    {
        $host = $this->host($url);
        $label = preg_replace('/\.(co\.uk|org\.uk|com|org|net|uk|ie)$/i', '', $host) ?? $host;
        $label = str_replace(['-', '.'], ' ', $label);

        return ucwords($label);
    }
}
