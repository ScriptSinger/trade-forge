<?php

namespace App\Services\Bot\Strategy\Pipes;

use App\Services\Bot\Strategy\TradeContext;
use Closure;
use Illuminate\Support\Facades\Log;

class ApplyRiskManagement implements PipeContract
{
    public function handle(TradeContext $context, Closure $next): mixed
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
        $riskSettings = $context->bot->strategy->riskSettings;
        $slMult = $riskSettings->sl_multiplier ?? 1.5;
        $tpMult = $riskSettings->tp_multiplier ?? 3.0;

        $context->stopLoss = $entryPrice - ($atr * $slMult);
        $context->takeProfit = $entryPrice + ($atr * $tpMult);

        // Расчет объема
        $riskAmount = (float) $context->bot->risk_per_trade; 
        
        $priceRisk = abs($entryPrice - $context->stopLoss);

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
