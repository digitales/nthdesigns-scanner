<?php

namespace App\Enums;

enum ProspectFinancialStatus: string
{
    case Matched = 'matched';
    case NoMatch = 'no_match';
    case Dissolved = 'dissolved';
    case Caution = 'caution';

    public function label(): string
    {
        return match ($this) {
            self::Matched => 'Matched',
            self::NoMatch => 'No match',
            self::Dissolved => 'Dissolved',
            self::Caution => 'Caution',
        };
    }
}
