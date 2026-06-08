<?php

namespace App\Enums;

enum WebsiteUrlSource: string
{
    case Gbp = 'gbp';
    case GoogleCse = 'google_cse';
    case Brave = 'brave';
    case Operator = 'operator';
}
