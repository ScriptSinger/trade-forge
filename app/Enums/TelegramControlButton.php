<?php

declare(strict_types=1);

namespace App\Enums;

enum TelegramControlButton: string
{
    case StartBots = '▶️ Запуск';
    case StopBots = '⏹ Пауза';
    case Status = '📊 Статус';

    /**
     * @return list<list<string>>
     */
    public static function keyboardRows(): array
    {
        return [
            [self::StartBots->value, self::StopBots->value, self::Status->value],
            ...TelegramLogChannel::keyboardRows(),
        ];
    }
}