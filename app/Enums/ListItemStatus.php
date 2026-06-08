<?php

namespace App\Enums;

enum ListItemStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Replied = 'replied';
    case Booked = 'booked';
    case ClosedWon = 'closed_won';
    case ClosedLost = 'closed_lost';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Contacted => 'Contacted',
            self::Replied => 'Replied',
            self::Booked => 'Booked',
            self::ClosedWon => 'Closed won',
            self::ClosedLost => 'Closed lost',
        };
    }
}
