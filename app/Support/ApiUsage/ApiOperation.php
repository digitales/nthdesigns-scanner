<?php

namespace App\Support\ApiUsage;

final class ApiOperation
{
    public const GOOGLE_PLACES_TEXT_SEARCH = 'google_places.text_search';

    public const GOOGLE_PLACES_PLACE_DETAILS = 'google_places.place_details';

    public const BRAVE_WEB_SEARCH = 'brave.web_search';

    /**
     * @return list<array{key: string, provider: string, operation: string, label: string}>
     */
    public static function all(): array
    {
        return [
            [
                'key' => self::GOOGLE_PLACES_TEXT_SEARCH,
                'provider' => 'google_places',
                'operation' => 'text_search',
                'label' => 'Places Text Search',
            ],
            [
                'key' => self::GOOGLE_PLACES_PLACE_DETAILS,
                'provider' => 'google_places',
                'operation' => 'place_details',
                'label' => 'Places Details',
            ],
            [
                'key' => self::BRAVE_WEB_SEARCH,
                'provider' => 'brave',
                'operation' => 'web_search',
                'label' => 'Brave Web Search',
            ],
        ];
    }

    /**
     * @return array{provider: string, operation: string}
     */
    public static function parse(string $key): array
    {
        [$provider, $operation] = explode('.', $key, 2);

        return compact('provider', 'operation');
    }

    public static function settingsColumn(string $provider, string $operation, string $periodType): string
    {
        return sprintf('%s_%s_%s', $provider, $operation, $periodType);
    }
}
