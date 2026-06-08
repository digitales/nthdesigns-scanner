<?php

namespace App\Enums;

enum IgnoredProspectReason: string
{
    case Acquired = 'acquired';
    case Cold = 'cold';
    case OutreachFailed = 'outreach_failed';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Acquired => 'Company acquired',
            self::Cold => 'Cold lead',
            self::OutreachFailed => 'Outreach did not work',
            self::Other => 'Other',
        };
    }
}
