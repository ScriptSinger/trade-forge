<?php

declare(strict_types=1);

namespace App\Enums;

enum StrategyMode: int
{
    case Surfer = 1;
    case Hybrid = 2;
    case SmartSurfer = 3;
    case SmartHybrid = 4;

    public function label(): string
    {
        return match ($this) {
            self::Surfer => '1 — Серфер',
            self::Hybrid => '2 — Гибрид',
            self::SmartSurfer => '3 — Умный Серфер',
            self::SmartHybrid => '4 — Умный Гибрид (рекомендуется)',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}