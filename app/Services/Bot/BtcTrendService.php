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

        if (empty($candles)) {
            return false;
        }

        $closePrices = array_map(
            fn (array $candle) => (float) $candle[4],
            array_reverse($candles),
        );

        $emaFast = end($this->indicators->ema($closePrices, (int) $filter->ema_fast)) ?: 0;
        $emaSlow = end($this->indicators->ema($closePrices, (int) $filter->ema_slow)) ?: 0;

        if ($emaFast <= 0 || $emaSlow <= 0) {
            return false;
        }

        return $emaFast > $emaSlow;
    }
}