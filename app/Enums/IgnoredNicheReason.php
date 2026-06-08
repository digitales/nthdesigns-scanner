<?php

namespace App\Enums;

enum IgnoredNicheReason: string
{
    case Manual = 'manual';
    case LowResults = 'low_results';
}
