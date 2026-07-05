<?php

namespace App\Services\Bot;

use App\Models\Bot;
use App\Services\Exchange\BybitExchangeService;

class PositionSizingService
{
    private const MIN_ORDER_USDT = 5.0;
    private const MAX_BALANCE_PCT = 0.30;
    private const FREE_BALANCE_BUFFER = 0.98;

    public function __construct(
        private BybitExchangeService $exchange,
    ) {}

    /**
     * Base-asset quantity aligned with Python buy(): balance × RISK / (entry - sl), capped.
     */
    public function calculateQuantity(Bot $bot, float $entryPrice, float $stopLoss): ?float
    {
        if ($entryPrice <= 0 || $stopLoss >= $entryPrice) {
            return null;
        }

        $balance = $this->exchange->getUsdtWalletBalance($bot->exchangeAccount);

        if ($balance === null || $balance <= 0) {
            return null;
        }

        $riskFraction = (float) (
            $bot->strategy->riskSettings?->max_risk_per_trade
            ?? $bot->risk_per_trade
            ?? 0.02
        );

        $riskAmountUsdt = $balance * $riskFraction;
        $priceRisk = $entryPrice - $stopLoss;

        if ($priceRisk <= 0) {
            return null;
        }

        $size = $riskAmountUsdt / $priceRisk;
        $costUsdt = $size * $entryPrice;

        $maxCost = $balance * self::MAX_BALANCE_PCT;
        if ($costUsdt > $maxCost) {
            $costUsdt = $maxCost;
        }

        $availableUsdt = $balance * self::FREE_BALANCE_BUFFER;
        if ($costUsdt > $availableUsdt) {
            $costUsdt = $availableUsdt;
        }

        if ($costUsdt < self::MIN_ORDER_USDT) {
            return null;
        }

        return $costUsdt / $entryPrice;
    }
}