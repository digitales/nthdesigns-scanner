<?php

namespace App\Enums;

enum SuppressionSource: string
{
    case Operator = 'operator';
    case SelfService = 'self_service';
}
