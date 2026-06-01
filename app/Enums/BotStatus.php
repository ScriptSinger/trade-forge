<?php

declare(strict_types=1);

namespace App\Enums;

enum BotStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Archived = 'archived';
}
