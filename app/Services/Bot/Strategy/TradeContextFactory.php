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
        $entrySettings = $bot->strategy->entrySettings;
        $interval = (string) ($entrySettings?->interval ?? 15);
        $klineLimit = (int) ($entrySettings?->kline_limit ?? BybitExchangeService::DEFAULT_KLINE_LIMIT);

        $market = $this->exchange->getMarketData(
            $bot->exchangeAccount,
            $symbol,
            $interval,
            $klineLimit,
        );

        return new TradeContext(
            bot: $bot,
            symbol: $symbol,
            candles: $market['candles'] ?? [],
            entryInterval: $interval,
        );
    }
}