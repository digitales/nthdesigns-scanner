<?php

namespace App\Enums;

enum IgnoredProspectReason: string
{
    case Acquired = 'acquired';
    case Cold = 'cold';
    case OutreachFailed = 'outreach_failed';
    case Unsubscribed = 'unsubscribed';
    case Reviewed = 'reviewed';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Acquired => 'Company acquired',
            self::Cold => 'Cold lead',
            self::OutreachFailed => 'Outreach did not work',
            self::Unsubscribed => 'Unsubscribed',
            self::Reviewed => 'Reviewed',
            self::Other => 'Other',
        };
    }
}
