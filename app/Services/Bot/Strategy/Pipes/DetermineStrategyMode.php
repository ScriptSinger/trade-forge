<?php

namespace App\Services\Bot\Strategy\Pipes;

use App\Enums\TradeSignal;
use App\Services\Bot\Concerns\ResolvesTradingLogger;
use App\Services\Bot\Strategy\Pipes\Concerns\BlocksTradeContext;
use App\Services\Bot\Strategy\TradeContext;
use Closure;

class DetermineStrategyMode implements PipeContract
{
    use BlocksTradeContext;
    use ResolvesTradingLogger;

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

        $this->tradingLog()->strategyDebug('Strategy mode determined', [
            'symbol' => $context->symbol,
            'mode' => $context->mode,
            'adx' => $adx,
            'rsi' => $rsi,
            'rsi_limit' => $rsiLimit,
        ]);

        if ($rsi > $rsiLimit) {
            return $this->block($context, "Overbought for {$context->mode} mode (RSI: {$rsi} > {$rsiLimit})");
        }

        $context->signal = TradeSignal::Buy;

        return $next($context);
    }
}
