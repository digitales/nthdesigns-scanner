<?php

namespace App\Enums;

enum ProspectValidatorStatus: string
{
    case HighChance = 'high_chance';
    case LowChance = 'low_chance';

    public function label(): string
    {
        return match ($this) {
            self::HighChance => 'High Chance',
            self::LowChance => 'Low Chance',
        };
    }
}
