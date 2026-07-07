<?php

namespace App\Services\Bot\Strategy\Pipes;

use App\Services\Bot\Concerns\ResolvesTradingLogger;
use App\Services\Bot\Strategy\Pipes\Concerns\BlocksTradeContext;
use App\Services\Bot\Strategy\TradeContext;
use App\Services\Strategy\TechnicalIndicatorService;
use Closure;

class CalculateIndicators implements PipeContract
{
    use BlocksTradeContext;
    use ResolvesTradingLogger;

    public function __construct(
        private TechnicalIndicatorService $indicators
    ) {}

    public function handle(TradeContext $context, Closure $next): mixed
    {
        $this->tradingLog()->strategyDebug('Calculating indicators', [
            'symbol' => $context->symbol,
        ]);

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

        $mappedCandles = array_reverse($mappedCandles);
        $context->candles = $mappedCandles;

        if (count($mappedCandles) < 3) {
            return $this->block($context, 'Insufficient candle history for indicators');
        }

        $closePrices = array_column($mappedCandles, 'close');
        $entrySettings = $context->bot->strategy->entrySettings;
        $period = (int) ($entrySettings->period ?? 20);

        $emaFastPeriod = (int) ($entrySettings->ema_fast ?? 50);
        $emaSlowPeriod = (int) ($entrySettings->ema_slow ?? 200);
        $context->indicators['ema_fast'] = $this->indicators->ema($closePrices, $emaFastPeriod);
        $context->indicators['ema_slow'] = $this->indicators->ema($closePrices, $emaSlowPeriod);
        $context->indicators['adx'] = $this->indicators->adx($mappedCandles, 14);
        $context->indicators['rsi'] = $this->indicators->rsi($closePrices, 14);
        $context->indicators['atr'] = $this->indicators->atr($mappedCandles, 14);

        $withoutLast = array_slice($mappedCandles, 0, -1);
        $lookback = array_slice($withoutLast, -$period);

        $context->indicators['prev_resistance'] = ! empty($lookback)
            ? max(array_column($lookback, 'high'))
            : 0;

        $context->indicators['prev_avg_volume'] = ! empty($lookback)
            ? array_sum(array_column($lookback, 'vol')) / count($lookback)
            : 0;

        return $next($context);
    }
}
