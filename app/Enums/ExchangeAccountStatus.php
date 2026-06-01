<?php

declare(strict_types=1);

namespace App\Enums;

enum ExchangeAccountStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';
    case Error = 'error';
}
