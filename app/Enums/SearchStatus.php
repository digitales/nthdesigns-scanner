<?php

namespace App\Enums;

enum SearchStatus: string
{
    case Pending = 'pending';
    case Discovering = 'discovering';
    case Auditing = 'auditing';
    case Complete = 'complete';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Complete, self::Failed], true);
    }
}
