<?php

declare(strict_types=1);

namespace App\Services\Bot;

use App\Enums\StrategyMode;
use App\Models\StrategyEntrySettings;

class StrategyModeResolver
{
    public function fromSettings(?StrategyEntrySettings $settings): StrategyMode
    {
        $mode = (int) ($settings?->strategy_mode ?? StrategyMode::SmartHybrid->value);

        return StrategyMode::tryFrom($mode) ?? StrategyMode::SmartHybrid;
    }

    /**
     * Python STRATEGY_MODE 1–4 runtime logic (SURFER / HYBRID / SNIPER).
     */
    public function resolveRuntime(StrategyMode $strategyMode, float $adx, int $trendThreshold): string
    {
        $isTrend = $adx > $trendThreshold;

        return match ($strategyMode) {
            StrategyMode::Surfer => 'Surfer',
            StrategyMode::Hybrid => 'Hybrid',
            StrategyMode::SmartSurfer => $isTrend ? 'Surfer' : 'Sniper',
            StrategyMode::SmartHybrid => $isTrend ? 'Hybrid' : 'Sniper',
        };
    }
}
