<?php

namespace App\Enums;

enum SearchSource: string
{
    case Discovery = 'discovery';
    case DirectUrl = 'direct_url';
}
