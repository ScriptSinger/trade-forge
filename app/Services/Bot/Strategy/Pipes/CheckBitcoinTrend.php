<?php

namespace App\Services\Bot\Strategy\Pipes;

use App\Services\Bot\Strategy\TradeContext;
use App\Services\Exchange\BybitExchangeService;
use App\Services\Strategy\TechnicalIndicatorService;
use Closure;
use Illuminate\Support\Facades\Log;

class CheckBitcoinTrend implements PipeContract
{
    public function __construct(
        private BybitExchangeService $exchange,
        private TechnicalIndicatorService $indicators
    ) {}

    public function handle(TradeContext $context, Closure $next): mixed
    {
        // Проверяем, включена ли опция в настройках бота/стратегии
        $checkBtc = $context->bot->strategy->settings['check_btc_trend'] ?? true;
        
        if (!$checkBtc) {
            return $next($context);
        }

        Log::info("Pipeline: Checking Bitcoin trend...");

        // Получаем данные по BTC/USDT (1h таймфрейм)
        $account = $context->bot->exchangeAccount;
        $market = $this->exchange->getMarketData($account, 'BTCUSDT', '60');
        $candles = $market['candles'] ?? [];

        if (empty($candles)) {
            Log::warning("Pipeline: Could not fetch BTC data.");
            return $next($context); // Пропускаем, если не смогли получить данные
        }

        $closePrices = array_map(fn($c) => (float) $c[4], array_reverse($candles));
        
        $ema50Arr = $this->indicators->ema($closePrices, 50);
        $ema200Arr = $this->indicators->ema($closePrices, 200);
        
        $ema50 = end($ema50Arr);
        $ema200 = end($ema200Arr);

        if ($ema50 < $ema200) {
            $context->isBlocked = true;
            $context->reason = "Bitcoin is bearish (EMA50 < EMA200)";
            return $context;
        }

        Log::info("Pipeline: Bitcoin is bullish. Proceeding.");
        return $next($context);
    }
}
