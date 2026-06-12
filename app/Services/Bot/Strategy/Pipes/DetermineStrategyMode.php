<?php

namespace App\Services\Bot\Strategy\Pipes;

use App\Services\Bot\Strategy\TradeContext;
use App\Enums\TradeSignal;
use Closure;
use Illuminate\Support\Facades\Log;

class DetermineStrategyMode
{
    public function handle(TradeContext $context, Closure $next)
    {
        $adxArr = $context->indicators['adx'];
        $adx = end($adxArr) ?: 0;
        
        $rsiArr = $context->indicators['rsi'];
        $rsi = end($rsiArr) ?: 50;

        // Определяем режим по ADX
        // Режим «Снайпер» (ADX от 20 до 30)
        // Режим «Умный Гибрид» (ADX > 30)
        
        if ($adx > 30) {
            $context->mode = 'Hybrid';
            $rsiLimit = 75;
        } else {
            $context->mode = 'Sniper';
            $rsiLimit = 55;
        }

        Log::info("Pipeline: Mode determined as {$context->mode} (ADX: {$adx}, RSI: {$rsi}, Limit: {$rsiLimit})");

        // Проверка RSI
        if ($rsi > $rsiLimit) {
            $context->isBlocked = true;
            $context->reason = "Overbought for {$context->mode} mode (RSI: {$rsi} > {$rsiLimit})";
            return $context;
        }

        // Если прошли проверку, устанавливаем предварительный сигнал BUY
        $context->signal = TradeSignal::Buy;

        return $next($context);
    }
}
