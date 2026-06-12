<?php

namespace App\Services\Bot;

use App\Services\Exchange\BybitExchangeService;
use App\Models\ExchangeAccount;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MarketScannerService
{
    public function __construct(
        private BybitExchangeService $exchange
    ) {}

    /**
     * Get the top 30 most volatile spot USDT pairs with volume > 5,000,000 USDT.
     */
    public function getTopVolatileSymbols(ExchangeAccount $account): array
    {
        $cacheKey = 'bot_top_volatile_symbols_' . $account->id;

        return Cache::remember($cacheKey, 600, function () use ($account) {
            Log::info("MarketScanner: Scanning Bybit for volatile assets...");

            $allTickers = $this->exchange->getAllTickers($account);

            if (empty($allTickers)) {
                Log::warning("MarketScanner: No tickers received from Bybit.");
                return [];
            }

            $symbols = collect($allTickers)
                // Сито №1 & №2: Только USDT, не стейблы и Объем > 5,000,000 USDT
                ->filter(function ($ticker) {
                    $symbol = $ticker['symbol'] ?? '';
                    // В Bybit V5 turnover24h - это объем в котируемой валюте (USDT)
                    $volume = (float) ($ticker['turnover24h'] ?? 0);

                    $isUsdt = str_ends_with($symbol, 'USDT');
                    $isStable = str_contains($symbol, 'USDC') ||
                        str_contains($symbol, 'DAI') ||
                        str_contains($symbol, 'BUSD') ||
                        str_contains($symbol, 'EUR');

                    return $isUsdt && !$isStable && $volume >= 5000000;
                })
                // Сито №3: Считаем волатильность за 24ч
                ->map(function ($ticker) {
                    $high = (float) ($ticker['highPrice24h'] ?? 0);
                    $low = (float) ($ticker['lowPrice24h'] ?? 0);

                    $volatility = $low > 0 ? (($high - $low) / $low) * 100 : 0;

                    return [
                        'symbol' => $ticker['symbol'],
                        'volatility' => $volatility,
                        'volume' => (float) ($ticker['turnover24h'] ?? 0),
                        'price' => (float) ($ticker['lastPrice'] ?? 0),
                    ];
                })
                // Ранжирование
                ->sortByDesc('volatility')
                ->take(30)
                ->values()
                ->toArray();

            Log::info("MarketScanner: Found " . count($symbols) . " volatile symbols.");

            return $symbols;
        });
    }
}
