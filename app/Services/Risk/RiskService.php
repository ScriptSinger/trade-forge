<?php

namespace App\Services\Risk;

use App\Enums\PositionStatus;
use App\Enums\TradeSignal;
use App\Models\Bot;

class RiskService
{
    /**
     * Check if a trade is allowed based on risk parameters.
     */
    public function allowTrade(Bot $bot, TradeSignal|string $signal): bool
    {
        // If it's a HOLD signal, we don't need to check risk limits for entry
        if ($signal === TradeSignal::Hold || (is_string($signal) && strtolower($signal) === 'hold')) {
            return true;
        }

        // Check maximum open positions limit
        $openPositionsCount = $bot->positions()
            ->where('status', PositionStatus::Open)
            ->count();

        if ($openPositionsCount >= $bot->max_open_positions) {
            return false;
        }

        return true;
    }

    /**
     * Calculate the size of the order based on bot settings.
     */
    public function calculateSize(Bot $bot): float
    {
        // Currently returns the fixed risk_per_trade amount defined in bot settings.
        // In the future, this can be expanded to calculate size based on account balance or % of equity.
        return (float) $bot->risk_per_trade;
    }
}
