<?php

namespace App\Enums;

enum AuditStatus: string
{
    case Pending = 'pending';
    case Complete = 'complete';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function isPending(): bool
    {
        return $this === self::Pending;
    }
}
