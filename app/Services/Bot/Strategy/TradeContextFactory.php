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
        $interval = $bot->strategy->entrySettings?->interval ?? '15';

        $market = $this->exchange->getMarketData(
            $bot->exchangeAccount,
            $symbol,
            $interval
        );

        return new TradeContext(
            bot: $bot,
            symbol: $symbol,
            candles: $market['candles'] ?? [],
            entryInterval: $interval,
        );
    }
}