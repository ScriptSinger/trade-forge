<?php

namespace App\Services\Bot\Market;

use App\Models\Bot;
use App\Services\Bot\Engine\TradingLogger;
use App\Services\Exchange\Bybit\BybitExchangeService;
use Illuminate\Support\Facades\Cache;

class MarketScannerService
{
    public function __construct(
        private BybitExchangeService $exchange,
        private TradingLogger $log,
    ) {}

    public function getTopVolatileSymbols(Bot $bot): array
    {
        $account = $bot->exchangeAccount;
        $ttl = (int) ($bot->strategy->riskSettings?->scanner_cache_ttl ?? 7200);
        $cacheKey = "market_scanner_top30_{$account->id}_{$bot->strategy_id}";

        return Cache::remember($cacheKey, $ttl, function () use ($account, $bot) {
            return $this->scanTopVolatileSymbols($account, $bot);
        });
    }

    private function scanTopVolatileSymbols($account, Bot $bot): array
    {
        $allTickers = $this->exchange->getAllTickers($account);

        if (empty($allTickers)) {
            $this->log->botWarning('MarketScanner failed: empty response', [
                'account_id' => $account->id,
            ]);

            return [];
        }

        $excludedPatterns = ScannerSymbolFilter::resolve(
            $bot->strategy->riskSettings?->scanner_excluded_patterns,
        );

        return collect($allTickers)
            ->filter(function ($ticker) use ($excludedPatterns) {
                $symbol = $ticker['symbol'] ?? '';
                $volume = (float) ($ticker['turnover24h'] ?? 0);

                $isUsdt = str_ends_with($symbol, 'USDT');
                $isExcluded = ScannerSymbolFilter::isExcluded($symbol, $excludedPatterns);

                return $isUsdt && ! $isExcluded && $volume >= 5000000;
            })
            ->map(function ($ticker) {
                $high = (float) ($ticker['highPrice24h'] ?? 0);
                $low = (float) ($ticker['lowPrice24h'] ?? 0);

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
    }
}