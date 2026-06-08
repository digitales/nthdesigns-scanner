<?php

namespace App\Enums;

enum AuditJobStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Complete = 'complete';
    case Failed = 'failed';
}
