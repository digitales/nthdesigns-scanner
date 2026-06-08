<?php

namespace App\Enums;

enum ScanType: string
{
    case GbpOnly = 'gbp_only';
    case AccessibilityOnly = 'accessibility_only';
    case Combined = 'combined';
}
