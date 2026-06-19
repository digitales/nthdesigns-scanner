<?php

namespace App\Support;

class QualificationFlagFormatter
{
    /** @var array<string, string> */
    private const LABELS = [
        'wrong_niche' => 'Wrong niche',
        'corporate_chain' => 'Corporate chain or franchise',
    ];

    public static function format(string $flag): string
    {
        if ($flag === '') {
            return $flag;
        }

        if (isset(self::LABELS[$flag])) {
            return self::LABELS[$flag];
        }

        if (! preg_match('/^[a-z][a-z0-9_]*$/', $flag)) {
            return $flag;
        }

        return ucwords(str_replace('_', ' ', $flag));
    }

    /**
     * @param  list<string>  $flags
     * @return list<string>
     */
    public static function formatMany(array $flags): array
    {
        return array_values(array_map(self::format(...), $flags));
    }

    public static function shouldPreserveRaw(string $flag): bool
    {
        return isset(self::LABELS[$flag]);
    }
}
