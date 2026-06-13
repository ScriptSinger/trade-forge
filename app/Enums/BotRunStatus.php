<?php

declare(strict_types=1);

namespace App\Enums;

enum BotRunStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Success = 'success';
    case Failed = 'failed';
    case Rejected = 'rejected';
}
