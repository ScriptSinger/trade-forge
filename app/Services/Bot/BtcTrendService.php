<?php

namespace App\Services\Bot;

use App\Models\Bot;
use App\Models\StrategyBtcTrendFilter;
use App\Services\Exchange\BybitExchangeService;
use App\Services\Strategy\TechnicalIndicatorService;

class BtcTrendService
{
    public function __construct(
        private BybitExchangeService $exchange,
        private TechnicalIndicatorService $indicators,
    ) {}

    public function isBullish(Bot $bot): bool
    {
        $filter = $bot->strategy?->btcTrendFilter;

        if (!$filter instanceof StrategyBtcTrendFilter || !$filter->enabled) {
            return true;
        }

        $candles = $this->exchange->getKlines(
            $bot->exchangeAccount,
            $filter->benchmark_symbol ?? 'BTCUSDT',
            (string) ($filter->benchmark_interval ?? 60),
        );

        return $this->isBullishFromCandles(
            $candles,
            (int) $filter->ema_fast,
            (int) $filter->ema_slow,
        );
    }

    public function isBullishFromCandles(array $rawCandles, int $emaFast, int $emaSlow): bool
    {
        if (empty($rawCandles)) {
            return false;
        }

        $closePrices = array_map(
            fn (array $candle) => (float) $candle[4],
            array_reverse($rawCandles),
        );

        $emaFastArr = $this->indicators->ema($closePrices, $emaFast);
        $emaSlowArr = $this->indicators->ema($closePrices, $emaSlow);

        $fast = end($emaFastArr) ?: 0;
        $slow = end($emaSlowArr) ?: 0;

        if ($fast <= 0 || $slow <= 0) {
            return false;
        }

        return $fast > $slow;
    }
}