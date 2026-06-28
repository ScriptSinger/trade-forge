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
     * Calculate the size of the order based on bot settings and current positions.
     */
    public function calculateSize(Bot $bot, $signal): float
    {
        $side = is_string($signal) ? strtolower($signal) : $signal->value;

        if ($side === 'sell') {
            // Calculate total quantity of all open positions for this bot.
            return (float) $bot->positions()
                ->where('status', PositionStatus::Open)
                ->sum('quantity');
        }

        // For BUY, we return the amount in USDT (risk_per_trade)
        return (float) $bot->risk_per_trade;
    }
}
