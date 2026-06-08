<?php

namespace App\Enums;

enum ProspectListType: string
{
    case Manual = 'manual';
    case Smart = 'smart';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Smart => 'Smart filter',
        };
    }
}
