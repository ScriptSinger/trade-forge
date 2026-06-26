<?php

namespace App\Services\Bot\Strategy\Pipes;

use App\Services\Bot\Strategy\TradeContext;
use App\Services\Strategy\TechnicalIndicatorService;
use Closure;
use Illuminate\Support\Facades\Log;

class CalculateIndicators implements PipeContract
{
    public function __construct(
        private TechnicalIndicatorService $indicators
    ) {}

    public function handle(TradeContext $context, Closure $next): mixed
    {
        Log::info("Pipeline: Calculating indicators for {$context->symbol}");

        // 1. Map raw Bybit candles to associative array [ts, open, high, low, close, vol]
        // Bybit returns: [startTime, openPrice, highPrice, lowPrice, closePrice, volume, turnover]
        $mappedCandles = array_map(function ($c) {
            return [
                'ts' => $c[0],
                'open' => (float) $c[1],
                'high' => (float) $c[2],
                'low' => (float) $c[3],
                'close' => (float) $c[4],
                'vol' => (float) $c[5],
            ];
        }, $context->candles);

        // Reverse to have chronological order (oldest first) if needed,
        // but Bybit v5 kline returns newest first.
        // Indicators usually need chronological order.
        $mappedCandles = array_reverse($mappedCandles);
        $context->candles = $mappedCandles;

        $closePrices = array_column($mappedCandles, 'close');
        $settings = $context->bot->strategy->settings;

        // 2. Calculate EMA Fast & Slow (dynamic)
        $emaFast = $settings['ema_fast'] ?? 50;
        $emaSlow = $settings['ema_slow'] ?? 200;
        $context->indicators['ema_fast'] = $this->indicators->ema($closePrices, (int) $emaFast);
        $context->indicators['ema_slow'] = $this->indicators->ema($closePrices, (int) $emaSlow);

        // 3. Calculate ADX 14
        $context->indicators['adx'] = $this->indicators->adx($mappedCandles, 14);

        // 4. Calculate RSI 14
        $context->indicators['rsi'] = $this->indicators->rsi($closePrices, 14);

        // 5. Calculate ATR 14
        $context->indicators['atr'] = $this->indicators->atr($mappedCandles, 14);

        // 6. Resistance (Max high of last N candles from 'period')
        $period = $settings['period'] ?? 20;
        $lookback = array_slice($mappedCandles, - ($period + 1), (int)$period);
        $context->indicators['resistance'] = !empty($lookback) ? max(array_column($lookback, 'high')) : 0;

        // 7. Average Volume (last N candles)
        $context->indicators['avg_volume'] = !empty($lookback) ? array_sum(array_column($lookback, 'vol')) / count($lookback) : 0;

        return $next($context);
    }
}
