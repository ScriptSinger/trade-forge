<?php

namespace App\Services\Bot\Strategy\Pipes;

use App\Services\Bot\Strategy\TradeContext;
use App\Enums\TradeSignal;
use Closure;
use Illuminate\Support\Facades\Log;

class ValidateTechnicalEntry implements PipeContract
{
    public function handle(TradeContext $context, Closure $next): mixed
    {
        $lastCandle = $context->lastCandle();
        $prevCandle = $context->prevCandle();
        
        $adxArr = $context->indicators['adx'];
        $adx = end($adxArr) ?: 0;
        
        $ema50Arr = $context->indicators['ema50'];
        $ema200Arr = $context->indicators['ema200'];
        $ema50 = end($ema50Arr) ?: 0;
        $ema200 = end($ema200Arr) ?: 0;
        
        $resistance = $context->indicators['resistance'];
        $avgVol = $context->indicators['avg_volume'];
        
        $currentClose = $lastCandle['close'];
        $currentVol = $lastCandle['vol'];

        $minAdx = $context->bot->strategy->settings['min_adx'] ?? 20;

        Log::info("Checking conditions for {$context->symbol}: ADX:{$adx} (Min:{$minAdx}), EMA50:{$ema50}, EMA200:{$ema200}, Price:{$currentClose}, Res:{$resistance}, Vol:{$currentVol}, AvgVol:{$avgVol}");

        // 1. ADX > 20 (dynamic)
        if ($adx <= $minAdx) {
            $context->isBlocked = true;
            $context->reason = "Low ADX ({$adx} <= {$minAdx})";
            return $context; // Stop pipeline
        }

        // 2. EMA50 > EMA200
        if ($ema50 <= $ema200) {
            $context->isBlocked = true;
            $context->reason = "Bearish EMA alignment (50:{$ema50} <= 200:{$ema200})";
            return $context; // Stop pipeline
        }

        // 3. Breakout: Price > Resistance
        if ($currentClose <= $resistance) {
            $context->isBlocked = true;
            $context->reason = "No breakout (Price:{$currentClose} <= Resistance:{$resistance})";
            return $context; // Stop pipeline
        }

        // 4. Volume Spike: Current Vol > Average Vol
        if ($currentVol <= $avgVol) {
            $context->isBlocked = true;
            $context->reason = "Low volume (Vol:{$currentVol} <= Avg:{$avgVol})";
            return $context; // Stop pipeline
        }

        // All conditions met for Step 2!
        Log::info("Step 2 passed for {$context->symbol}!");
        return $next($context);
    }
}
