<?php

namespace App\Enums;

enum AuditJobType: string
{
    case GbpScore = 'gbp_score';
    case Accessibility = 'accessibility';
    case Lighthouse = 'lighthouse';
    case Screenshot = 'screenshot';
}
