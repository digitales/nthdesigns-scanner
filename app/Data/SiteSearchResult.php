<?php

namespace App\Data;

final class SiteSearchResult
{
    /**
     * @param  list<array{key: string, label: string, items: list<array{title: string, subtitle: string|null, href: string}>}>  $sections
     */
    public function __construct(
        public readonly string $status,
        public readonly array $sections = [],
    ) {}

    public static function tooShort(): self
    {
        return new self('too_short');
    }

    /**
     * @param  list<array{key: string, label: string, items: list<array{title: string, subtitle: string|null, href: string}>}>  $sections
     */
    public static function fromSections(array $sections): self
    {
        $nonEmpty = array_values(array_filter(
            $sections,
            fn (array $section): bool => $section['items'] !== [],
        ));

        return new self(
            $nonEmpty === [] ? 'empty' : 'results',
            $nonEmpty,
        );
    }
}
