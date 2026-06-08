<?php

namespace App\Enums;

enum WebsiteDiscoveryConfidence: string
{
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';
}
