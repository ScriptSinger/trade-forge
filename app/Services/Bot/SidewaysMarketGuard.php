<?php

namespace App\Services\Bot;

use App\Models\Bot;
use Illuminate\Support\Facades\Log;

class SidewaysMarketGuard
{
    public function __construct(
        private DailyPerformanceService $performance,
        private BtcTrendService $btcTrend,
    ) {}

    /**
     * Daily profit target reached while BTC is not in an uptrend.
     * Blocks new entries and triggers Sniper exits in PositionService.
     */
    public function blocksNewEntries(Bot $bot): bool
    {
        $bot->loadMissing(['strategy.riskSettings', 'strategy.btcTrendFilter', 'exchangeAccount']);

        $risk = $bot->strategy?->riskSettings;

        if (!$risk?->daily_target_enabled) {
            return false;
        }

        $startBalance = $this->performance->startBalance($bot);

        if ($startBalance <= 0) {
            Log::channel('bot')->debug('SidewaysMarketGuard: skipped, no start_balance', [
                'bot_id' => $bot->id,
            ]);

            return false;
        }

        $profitPct = $this->performance->profitPct($bot);
        $targetPct = (float) $risk->daily_profit_target_pct;

        if ($profitPct < $targetPct) {
            return false;
        }

        if ($this->btcTrend->isBullish($bot)) {
            Log::channel('bot')->info('SidewaysMarketGuard: target reached but BTC bullish, trading continues', [
                'bot_id' => $bot->id,
                'profit_pct' => round($profitPct, 2),
                'target_pct' => $targetPct,
            ]);

            return false;
        }

        Log::channel('bot')->info('SidewaysMarketGuard: blocking new entries', [
            'bot_id' => $bot->id,
            'profit_pct' => round($profitPct, 2),
            'target_pct' => $targetPct,
        ]);

        return true;
    }
}