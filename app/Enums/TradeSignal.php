<?php

declare(strict_types=1);

namespace App\Enums;

enum TradeSignal: string
{
    case Buy = 'buy';
    case Sell = 'sell';
    case Hold = 'hold';
}
