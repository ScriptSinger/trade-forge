<?php

declare(strict_types=1);

namespace App\Enums;

enum TelegramLogChannel: string
{
    case Bot = '🤖 Бот';
    case Exchange = '🔌 API';
    case Strategy = '📈 Стратегия';
    case Orders = '📦 Ордера';
    case Risk = '🛡 Риск';

    public function loggingChannel(): string
    {
        return match ($this) {
            self::Bot => 'bot',
            self::Exchange => 'exchange',
            self::Strategy => 'strategy',
            self::Orders => 'orders',
            self::Risk => 'risk',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Bot => 'бот',
            self::Exchange => 'API биржи',
            self::Strategy => 'стратегия',
            self::Orders => 'ордера',
            self::Risk => 'риск',
        };
    }

    /**
     * @return list<list<string>>
     */
    public static function keyboardRows(): array
    {
        return [
            [self::Bot->value, self::Exchange->value, self::Strategy->value],
            [self::Orders->value, self::Risk->value],
        ];
    }
}
