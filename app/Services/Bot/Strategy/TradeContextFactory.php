<?php

namespace App\Services\Bot\Strategy;

use App\Models\Bot;
use App\Services\Exchange\BybitExchangeService;

class TradeContextFactory
{
    public function __construct(
        private BybitExchangeService $exchange
    ) {}

    public function make(Bot $bot, string $symbol): TradeContext
    {
        $strategy = $bot->strategy;
        $entrySettings = $strategy->entrySettings;
        $btcFilter = $strategy->btcTrendFilter;

        $interval = $entrySettings?->interval ?? '15';

        $market = $this->exchange->getMarketData(
            $bot->exchangeAccount,
            $symbol,
            $interval
        );

        $btcMarket = $this->exchange->getMarketData(
            $bot->exchangeAccount,
            $btcFilter?->benchmark_symbol ?? 'BTCUSDT',
            $btcFilter?->benchmark_interval ?? '15'
        );

        return new TradeContext(
            bot: $bot,
            symbol: $symbol,
            candles: $market['candles'] ?? [],
            btcCandles: $btcMarket['candles'] ?? [],

            btcTrendEnabled: $btcFilter?->enabled ?? false,
            btcEmaFast: (int) ($btcFilter?->ema_fast ?? 50),
            btcEmaSlow: (int) ($btcFilter?->ema_slow ?? 200),
            btcBenchmarkSymbol: $btcFilter?->benchmark_symbol ?? 'BTCUSDT',
            btcBenchmarkInterval: $btcFilter?->benchmark_interval ?? '15',
            entryInterval: $interval,
        );
    }
}
