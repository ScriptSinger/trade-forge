<?php

declare(strict_types=1);

namespace App\Enums;

enum PositionStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case Liquidated = 'liquidated';
}
