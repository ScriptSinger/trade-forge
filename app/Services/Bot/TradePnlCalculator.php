<?php

namespace App\Services\Bot;

class TradePnlCalculator
{
    public function calculate(
        float $entryPrice,
        float $exitPrice,
        float $quantity,
        float $feeRate = 0.001,
    ): array {
        $entryCostUsdt = $entryPrice * $quantity;
        $exitValueUsdt = $exitPrice * $quantity;
        $fees = ($entryCostUsdt * $feeRate) + ($exitValueUsdt * $feeRate);
        $netProfitUsdt = ($exitValueUsdt - $entryCostUsdt) - $fees;
        $netPnlPct = $entryCostUsdt > 0 ? ($netProfitUsdt / $entryCostUsdt) * 100 : 0.0;

        return [
            'profit_loss' => $netProfitUsdt,
            'profit_percent' => $netPnlPct,
            'fees' => $fees,
        ];
    }
}
