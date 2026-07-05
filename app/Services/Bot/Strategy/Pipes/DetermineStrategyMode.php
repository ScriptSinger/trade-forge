<?php

namespace App\Services\Bot\Strategy\Pipes;

use App\Enums\TradeSignal;
use App\Services\Bot\Strategy\Pipes\Concerns\BlocksTradeContext;
use App\Services\Bot\Strategy\TradeContext;
use Closure;
use Illuminate\Support\Facades\Log;

class DetermineStrategyMode implements PipeContract
{
    use BlocksTradeContext;

    public function handle(TradeContext $context, Closure $next): mixed
    {
        $entrySettings = $context->bot->strategy->entrySettings;

        $adxArr = $context->indicators['adx'] ?? [];
        $adx = end($adxArr) ?: 0;

        $rsiArr = $context->indicators['rsi'] ?? [];
        $rsi = end($rsiArr) ?: 50;

        $trendThreshold = (int) ($entrySettings->trend_adx_threshold ?? 30);
        $rsiSniper = (float) ($entrySettings->rsi_limit_sniper ?? 55);
        $rsiHybrid = (float) ($entrySettings->rsi_limit_hybrid ?? 75);

        if ($adx > $trendThreshold) {
            $context->mode = 'Hybrid';
            $rsiLimit = $rsiHybrid;
        } else {
            $context->mode = 'Sniper';
            $rsiLimit = $rsiSniper;
        }

        Log::info("Pipeline: Mode determined as {$context->mode} (ADX: {$adx}, RSI: {$rsi}, Limit: {$rsiLimit})");

        if ($rsi > $rsiLimit) {
            return $this->block($context, "Overbought for {$context->mode} mode (RSI: {$rsi} > {$rsiLimit})");
        }

        $context->signal = TradeSignal::Buy;

        return $next($context);
    }
}