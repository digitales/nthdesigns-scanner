<?php

namespace App\Enums;

enum NicheScanStatus: string
{
    case Pending = 'pending';
    case Complete = 'complete';
    case Failed = 'failed';
}
