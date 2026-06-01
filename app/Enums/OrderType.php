<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderType: string
{
    case Market = 'market';
    case Limit = 'limit';
}
