<?php

namespace App\Services\Bot;

use App\Models\Bot;
use App\Models\StrategyRiskSettings;
use App\Services\Exchange\BybitExchangeService;

class PositionSizingService
{
    public function __construct(
        private BybitExchangeService $exchange,
    ) {}

    public function calculateQuantity(Bot $bot, string $symbol, float $entryPrice, float $stopLoss): ?float
    {
        if ($entryPrice <= 0 || $stopLoss >= $entryPrice) {
            return null;
        }

        $risk = $bot->strategy->riskSettings;
        $balance = $this->exchange->getUsdtBalance($bot->exchangeAccount);

        if ($balance === null || $balance->wallet <= 0) {
            return null;
        }

        $totalBalance = $balance->wallet;

        $riskFraction = (float) ($risk?->max_risk_per_trade ?? 0.02);

        $riskAmountUsdt = $totalBalance * $riskFraction;
        $priceRisk = $entryPrice - $stopLoss;

        if ($priceRisk <= 0) {
            return null;
        }

        $size = $riskAmountUsdt / $priceRisk;
        $costUsdt = $size * $entryPrice;

        $maxCost = $totalBalance * $this->maxBalancePct($risk);
        if ($costUsdt > $maxCost) {
            $costUsdt = $maxCost;
        }

        $freeUsdt = $balance->free;
        $freeCap = $freeUsdt * $this->freeBalanceBuffer($risk);
        if ($costUsdt > $freeCap) {
            $costUsdt = $freeCap;
        }

        $minOrderUsdt = $this->minOrderUsdt($risk);
        if ($costUsdt < $minOrderUsdt) {
            return null;
        }

        $quantity = $costUsdt / $entryPrice;
        $normalized = $this->exchange->normalizeQuantity($bot->exchangeAccount, $symbol, $quantity);

        if ($normalized <= 0 || ($normalized * $entryPrice) < $minOrderUsdt) {
            return null;
        }

        return $normalized;
    }

    private function minOrderUsdt(?StrategyRiskSettings $risk): float
    {
        return (float) ($risk?->min_order_usdt ?? 5.0);
    }

    private function maxBalancePct(?StrategyRiskSettings $risk): float
    {
        return (float) ($risk?->max_balance_pct ?? 0.30);
    }

    private function freeBalanceBuffer(?StrategyRiskSettings $risk): float
    {
        return (float) ($risk?->free_balance_buffer ?? 0.98);
    }
}
