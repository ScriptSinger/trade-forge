<?php

declare(strict_types=1);

namespace App\Enums;

enum StrategyType: string
{
    case Breakout = 'breakout';
    case Hybrid = 'hybrid';
    case Trend = 'trend';
}
