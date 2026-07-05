<?php

namespace App\Services\Bot;

use App\Services\Exchange\BybitExchangeService;
use App\Models\ExchangeAccount;

class MarketScannerService
{
    public function __construct(
        private BybitExchangeService $exchange,
        private TradingLogger $log,
    ) {}

    /**
     * Get the top 30 most volatile spot USDT pairs with volume > 5,000,000 USDT.
     */
    public function getTopVolatileSymbols(ExchangeAccount $account): array
    {
        $allTickers = $this->exchange->getAllTickers($account);

        if (empty($allTickers)) {
            $this->log->botWarning('MarketScanner failed: empty response', [
                'account_id' => $account->id,
            ]);
            return [];
        }

        $symbols = collect($allTickers)
            ->filter(function ($ticker) {
                $symbol = $ticker['symbol'] ?? '';
                $volume = (float) ($ticker['turnover24h'] ?? 0);

                $isUsdt = str_ends_with($symbol, 'USDT');

                $isStable = str_contains($symbol, 'USDC')
                    || str_contains($symbol, 'DAI')
                    || str_contains($symbol, 'BUSD')
                    || str_contains($symbol, 'EUR');

                return $isUsdt && !$isStable && $volume >= 5000000;
            })
            ->map(function ($ticker) {
                $high = (float) ($ticker['highPrice24h'] ?? 0);
                $low  = (float) ($ticker['lowPrice24h'] ?? 0);

                $volatility = $low > 0
                    ? (($high - $low) / $low) * 100
                    : 0;

                return [
                    'symbol' => $ticker['symbol'],
                    'volatility' => $volatility,
                    'volume' => (float) ($ticker['turnover24h'] ?? 0),
                    'price' => (float) ($ticker['lastPrice'] ?? 0),
                ];
            })
            ->sortByDesc('volatility')
            ->take(30)
            ->values()
            ->toArray();

        return $symbols;
    }
}