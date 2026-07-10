<?php

namespace App\Services\Bot\Risk;

use App\Models\Bot;
use App\Models\StrategyRiskSettings;
use App\Services\Exchange\Balance\AccountBalanceSnapshot;
use App\Services\Exchange\Bybit\BybitExchangeService;

class PositionSizingService
{
    public function __construct(
        private BybitExchangeService $exchange,
    ) {}

    public function calculateQuantity(Bot $bot, string $symbol, float $entryPrice, float $stopLoss): SizingResult
    {
        $risk = $bot->strategy->riskSettings;
        $riskFraction = (float) ($risk?->max_risk_per_trade ?? 0.02);
        $minOrderUsdt = $this->minOrderUsdt($risk);
        $maxBalancePct = $this->maxBalancePct($risk);
        $freeBalanceBuffer = $this->freeBalanceBuffer($risk);

        $debug = [
            'symbol' => $symbol,
            'entry_price' => $entryPrice,
            'stop_loss' => $stopLoss,
            'risk_fraction' => $riskFraction,
            'min_order_usdt' => $minOrderUsdt,
            'max_balance_pct' => $maxBalancePct,
            'free_balance_buffer' => $freeBalanceBuffer,
        ];

        if ($entryPrice <= 0 || $stopLoss >= $entryPrice) {
            return $this->reject(SizingResult::REASON_INVALID_PRICES, $debug);
        }

        $balance = $this->exchange->getUsdtBalance($bot->exchangeAccount);
        $debug = array_merge($debug, $this->balanceDebug($balance));

        if ($balance === null || $balance->wallet <= 0) {
            return $this->reject(SizingResult::REASON_WALLET_EMPTY, $debug);
        }

        $totalBalance = $balance->wallet;
        $riskAmountUsdt = $totalBalance * $riskFraction;
        $priceRisk = $entryPrice - $stopLoss;

        $debug['risk_amount_usdt'] = $riskAmountUsdt;
        $debug['price_risk'] = $priceRisk;

        if ($priceRisk <= 0) {
            return $this->reject(SizingResult::REASON_INVALID_PRICE_RISK, $debug);
        }

        $size = $riskAmountUsdt / $priceRisk;
        $costUsdt = $size * $entryPrice;

        $debug['raw_cost_usdt'] = $costUsdt;

        $maxCost = $totalBalance * $maxBalancePct;
        $debug['max_cost_usdt'] = $maxCost;

        if ($costUsdt > $maxCost) {
            $costUsdt = $maxCost;
            $debug['capped_by'] = 'max_balance_pct';
        }

        $freeUsdt = $balance->free;
        $freeCap = $freeUsdt * $freeBalanceBuffer;
        $debug['free_usdt'] = $freeUsdt;
        $debug['free_cap_usdt'] = $freeCap;

        if ($costUsdt > $freeCap) {
            $costUsdt = $freeCap;
            $debug['capped_by'] = 'free_balance';
        }

        $debug['final_cost_usdt'] = $costUsdt;

        if ($costUsdt < $minOrderUsdt) {
            return $this->reject(SizingResult::REASON_BELOW_MIN_ORDER, $debug);
        }

        $quantity = $costUsdt / $entryPrice;
        $normalized = $this->exchange->normalizeQuantity($bot->exchangeAccount, $symbol, $quantity);

        $debug['quantity_before_normalize'] = $quantity;
        $debug['quantity_normalized'] = $normalized;
        $debug['normalized_cost_usdt'] = $normalized * $entryPrice;

        if ($normalized <= 0 || ($normalized * $entryPrice) < $minOrderUsdt) {
            return $this->reject(SizingResult::REASON_BELOW_MIN_AFTER_NORMALIZE, $debug);
        }

        return new SizingResult(
            quantity: $normalized,
            reason: SizingResult::REASON_OK,
            debug: $debug,
        );
    }

    /**
     * @param  array<string, float|int|string|null>  $debug
     */
    private function reject(string $reason, array $debug): SizingResult
    {
        return new SizingResult(quantity: null, reason: $reason, debug: $debug);
    }

    /**
     * @return array<string, float|string|null>
     */
    private function balanceDebug(?AccountBalanceSnapshot $balance): array
    {
        if ($balance === null) {
            return [
                'wallet' => null,
                'free' => null,
                'locked' => null,
                'free_source' => null,
            ];
        }

        return [
            'wallet' => $balance->wallet,
            'free' => $balance->free,
            'locked' => $balance->locked,
            'free_source' => $balance->freeSource,
        ];
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