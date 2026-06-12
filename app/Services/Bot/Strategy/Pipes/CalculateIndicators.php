<?php

namespace App\Services\Bot\Strategy\Pipes;

use App\Services\Bot\Strategy\TradeContext;
use App\Services\Strategy\TechnicalIndicatorService;
use Closure;
use Illuminate\Support\Facades\Log;

class CalculateIndicators
{
    public function __construct(
        private TechnicalIndicatorService $indicators
    ) {}

    public function handle(TradeContext $context, Closure $next)
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

        // 2. Calculate EMA 50 & 200
        $context->indicators['ema50'] = $this->indicators->ema($closePrices, 50);
        $context->indicators['ema200'] = $this->indicators->ema($closePrices, 200);

        // 3. Calculate ADX 14
        $context->indicators['adx'] = $this->indicators->adx($mappedCandles, 14);

        // 4. Calculate RSI 14
        $context->indicators['rsi'] = $this->indicators->rsi($closePrices, 14);

        // 5. Calculate ATR 14
        $context->indicators['atr'] = $this->indicators->atr($mappedCandles, 14);

        // 6. Resistance (Max high of last 20 candles)
        $last20 = array_slice($mappedCandles, -21, 20); // Exclude current candle
        $context->indicators['resistance'] = !empty($last20) ? max(array_column($last20, 'high')) : 0;

        // 7. Average Volume (last 20 candles)
        $context->indicators['avg_volume'] = !empty($last20) ? array_sum(array_column($last20, 'vol')) / count($last20) : 0;

        return $next($context);
    }
}
