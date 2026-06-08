<?php

namespace App\Services;

use App\Models\Tag;
use App\Models\User;

class TagService
{
    public function findOrCreate(User $user, string $name, ?string $color = null): Tag
    {
        $normalised = $this->normaliseName($name);

        $existing = Tag::query()
            ->where('user_id', $user->id)
            ->whereRaw('LOWER(name) = ?', [$normalised])
            ->first();

        if ($existing) {
            return $existing;
        }

        return Tag::create([
            'user_id' => $user->id,
            'name' => $normalised,
            'color' => $color,
        ]);
    }

    /**
     * @return list<string>
     */
    public function suggestionsFor(User $user): array
    {
        $existing = Tag::query()
            ->where('user_id', $user->id)
            ->orderBy('name')
            ->pluck('name')
            ->all();

        $suggested = config('prospect_lists.suggested_tags', []);

        return array_values(array_unique([...$suggested, ...$existing]));
    }

    public function normaliseName(string $name): string
    {
        return mb_strtolower(trim($name));
    }
}
