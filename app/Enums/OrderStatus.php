<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderStatus: string
{
    case New = 'new';
    case Placed = 'placed';
    case PartiallyFilled = 'partially_filled';
    case Filled = 'filled';
    case Cancelled = 'cancelled';
    case Rejected = 'rejected';
    case Failed = 'failed';
}
