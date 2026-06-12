<?php

namespace App\Services\Bot\Strategy\Pipes;

use App\Services\Bot\Strategy\TradeContext;
use Closure;
use Illuminate\Support\Facades\Log;

class ApplyRiskManagement
{
    public function handle(TradeContext $context, Closure $next)
    {
        $lastCandle = $context->lastCandle();
        $entryPrice = $lastCandle['close'];
        
        $atrArr = $context->indicators['atr'];
        $atr = end($atrArr) ?: 0;

        if ($atr <= 0) {
            $context->isBlocked = true;
            $context->reason = "Invalid ATR value";
            return $context;
        }

        // Множители из настроек стратегии или дефолтные
        // Обычно SL = 2 * ATR, TP = 3 * ATR (как пример)
        $slMult = $context->bot->strategy->settings['sl_multiplier'] ?? 2;
        $tpMult = $context->bot->strategy->settings['tp_multiplier'] ?? 3;

        $context->stopLoss = $entryPrice - ($atr * $slMult);
        $context->takeProfit = $entryPrice + ($atr * $tpMult);

        // Расчет объема (Риск 1% от депозита на сделку)
        // Риск в деньгах = депозит * 0.01
        // В нашем приложении risk_per_trade может быть уже суммой в USDT
        $riskAmount = (float) $context->bot->risk_per_trade; 
        
        $priceRisk = $entryPrice - $context->stopLoss;

        if ($priceRisk <= 0) {
            $context->isBlocked = true;
            $context->reason = "Invalid price risk calculation";
            return $context;
        }

        $context->quantity = $riskAmount / $priceRisk;

        Log::info("Pipeline: Risk applied for {$context->symbol}. SL: {$context->stopLoss}, TP: {$context->takeProfit}, Qty: {$context->quantity}");

        return $next($context);
    }
}
