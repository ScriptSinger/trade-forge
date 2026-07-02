<?php

namespace App\Services\Bot\Strategy\Pipes;

use App\Services\Bot\Strategy\TradeContext;
use App\Services\Exchange\BybitExchangeService;
use App\Services\Strategy\TechnicalIndicatorService;
use Closure;
use Illuminate\Support\Facades\Log;

class CheckBtcTrend implements PipeContract
{
    public function __construct(
        private BybitExchangeService $exchange,
        private TechnicalIndicatorService $indicators
    ) {}

    public function handle(TradeContext $context, Closure $next): mixed
    {
        // Проверяем, включена ли опция в настройках бота/стратегии
        $settings = $context->bot->strategy->settings ?? [];
        $checkBtcTrend = $settings['check_market_regime'] ?? $settings['check_btc_trend'] ?? true;

        if (!$checkBtcTrend) {
            return $next($context);
        }

        Log::info("Pipeline: Checking EMA fast/slow filter...");

        // Используем BTC как рыночный бенчмарк и EMA-периоды из настроек стратегии
        $candles = $context->btcCandles;

        if (empty($candles)) {
            $context->isBlocked = true;
            $context->reason = "Could not fetch BTC data for EMA fast/slow filter";
            return $context;
        }

        $closePrices = array_map(fn($c) => (float) $c[4], array_reverse($candles));
        $emaFastPeriod = (int) ($settings['ema_fast'] ?? 50);
        $emaSlowPeriod = (int) ($settings['ema_slow'] ?? 200);

        $emaFastArr = $this->indicators->ema($closePrices, $emaFastPeriod);
        $emaSlowArr = $this->indicators->ema($closePrices, $emaSlowPeriod);

        $emaFast = end($emaFastArr);
        $emaSlow = end($emaSlowArr);

        if ($emaFast < $emaSlow) {
            $context->isBlocked = true;
            $context->reason = "BTC trend bearish: EMA{$emaFastPeriod} < EMA{$emaSlowPeriod}";
            return $context;
        }

        Log::info("Pipeline: EMA{$emaFastPeriod} is above EMA{$emaSlowPeriod}. Proceeding.");
        return $next($context);
    }
}
