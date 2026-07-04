<?php

namespace App\Services\Bot\Strategy\Pipes;

use App\Services\Bot\Strategy\TradeContext;
use App\Services\Strategy\TechnicalIndicatorService;
use Closure;
use Illuminate\Support\Facades\Log;

class CheckBtcTrend implements PipeContract
{
    public function __construct(
        private TechnicalIndicatorService $indicators
    ) {}

    public function handle(TradeContext $context, Closure $next): mixed
    {
        if (!$context->btcTrendEnabled) {
            return $next($context);
        }

        Log::info("Pipeline: Checking EMA fast/slow filter...");

        $candles = $context->btcCandles;

        if (empty($candles)) {
            $context->isBlocked = true;
            $context->reason = "Could not fetch BTC data for EMA fast/slow filter";
            return $context;
        }

        $closePrices = array_map(fn($c) => (float) $c[4], array_reverse($candles));

        $emaFastArr = $this->indicators->ema($closePrices, $context->btcEmaFast);
        $emaSlowArr = $this->indicators->ema($closePrices, $context->btcEmaSlow);

        $emaFast = end($emaFastArr);
        $emaSlow = end($emaSlowArr);

        if ($emaFast < $emaSlow) {
            $context->isBlocked = true;
            $context->reason = "BTC trend bearish: EMA{$context->btcEmaFast} < EMA{$context->btcEmaSlow}";
            return $context;
        }

        Log::info("Pipeline: EMA{$context->btcEmaFast} is above EMA{$context->btcEmaSlow}. Proceeding.");
        return $next($context);
    }
}
