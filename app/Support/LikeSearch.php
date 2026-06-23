<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

final class LikeSearch
{
    public static function escape(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * @return list<string>
     */
    public static function tokens(string $query): array
    {
        $parts = preg_split('/\s+/', trim($query), -1, PREG_SPLIT_NO_EMPTY);

        return $parts === false ? [] : array_values($parts);
    }

    /**
     * @param  list<string>  $tokens
     * @param  list<string>  $columns
     */
    public static function applyTokens(Builder|Relation $query, array $tokens, array $columns): void
    {
        if ($query instanceof Relation) {
            $query = $query->getQuery();
        }

        foreach ($tokens as $token) {
            $pattern = '%'.self::escape($token).'%';
            $query->where(function (Builder $inner) use ($pattern, $columns): void {
                $first = true;
                foreach ($columns as $column) {
                    if ($first) {
                        self::whereColumnLike($inner, $column, $pattern);
                        $first = false;
                    } else {
                        $inner->orWhere(fn (Builder $nested) => self::whereColumnLike($nested, $column, $pattern));
                    }
                }
            });
        }
    }

    public static function whereColumnLike(Builder $query, string $column, string $pattern): Builder
    {
        return $query->whereRaw("{$column} LIKE ? ESCAPE '\\'", [$pattern]);
    }
}
