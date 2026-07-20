<?php

namespace App\Services\Bot\Performance;

use App\Models\Bot;
use App\Models\BotStat;
use App\Models\Trade;
use App\Services\Bot\Engine\TradingLogger;
use App\Services\Exchange\Bybit\BybitExchangeService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class DailyPerformanceService
{
    public function __construct(
        private BybitExchangeService $exchange,
        private TradingLogger $log,
    ) {}

    public function tradingDay(?CarbonInterface $at = null): CarbonInterface
    {
        $moment = $at ? Carbon::parse($at) : now();

        return $moment->timezone(config('app.timezone'))->startOfDay();
    }

    public function ensureTodayStat(Bot $bot): BotStat
    {
        $dateKey = $this->dateKey();

        $stat = BotStat::query()
            ->where('bot_id', $bot->id)
            ->whereDate('date', $dateKey)
            ->first();

        if (! $stat) {
            $stat = BotStat::query()->create([
                'bot_id' => $bot->id,
                'date' => $dateKey,
                'profit' => 0,
                'fees' => 0,
            ]);
        }

        if ($stat->start_balance !== null) {
            return $stat;
        }

        $balance = $this->exchange->getUsdtBalance($bot->exchangeAccount);

        if ($balance === null || $balance->wallet <= 0) {
            $this->log->botWarning('DailyPerformance: could not capture start_balance', [
                'bot_id' => $bot->id,
            ]);

            return $stat;
        }

        $stat->forceFill([
            'start_balance' => $balance->wallet,
            'start_balance_at' => now(),
        ])->save();

        return $stat;
    }

    public function profitUsdt(Bot $bot): float
    {
        return (float) $this->ensureTodayStat($bot)->profit;
    }

    public function startBalance(Bot $bot): float
    {
        return (float) ($this->ensureTodayStat($bot)->start_balance ?? 0);
    }

    public function profitPct(Bot $bot): float
    {
        $stat = $this->ensureTodayStat($bot);
        $startBalance = (float) ($stat->start_balance ?? 0);

        if ($startBalance <= 0) {
            return 0.0;
        }

        return ((float) $stat->profit / $startBalance) * 100;
    }

    public function recordPartialPnl(Bot $bot, float $profitLoss, float $fees): void
    {
        $stat = $this->ensureTodayStat($bot);

        $stat->forceFill([
            'profit' => (float) $stat->profit + $profitLoss,
            'fees' => (float) $stat->fees + $fees,
        ])->save();
    }

    public function refreshStartBalance(Bot $bot): void
    {
        $bot->loadMissing('exchangeAccount');

        $stat = $this->ensureTodayStat($bot);
        $balance = $this->exchange->getUsdtBalance($bot->exchangeAccount);

        if ($balance === null || $balance->wallet <= 0) {
            $this->log->botWarning('DailyPerformance: could not refresh start_balance', [
                'bot_id' => $bot->id,
            ]);

            return;
        }

        $stat->forceFill([
            'start_balance' => $balance->wallet,
            'start_balance_at' => now(),
        ])->save();
    }

    /**
     * Record a fully closed trade.
     *
     * When Hybrid partial legs already hit {@see recordPartialPnl}, pass those
     * amounts as $alreadyCountedPnl / $alreadyCountedFees so daily profit is
     * not double-counted. Win/loss uses the full trade PnL.
     */
    public function recordClosedTrade(
        Trade $trade,
        float $alreadyCountedPnl = 0.0,
        float $alreadyCountedFees = 0.0,
    ): void {
        $dateKey = $this->dateKey($trade->closed_at);

        $stat = BotStat::query()
            ->where('bot_id', $trade->bot_id)
            ->whereDate('date', $dateKey)
            ->first();

        if (! $stat) {
            $stat = BotStat::query()->create([
                'bot_id' => $trade->bot_id,
                'date' => $dateKey,
                'profit' => 0,
                'fees' => 0,
            ]);
        }

        $fullPnl = (float) $trade->profit_loss;
        $fullFees = (float) ($trade->fees ?? 0);
        $profitLoss = $fullPnl - $alreadyCountedPnl;
        $fees = $fullFees - $alreadyCountedFees;

        $totalTrades = $stat->total_trades + 1;
        $wins = $stat->wins + ($fullPnl >= 0 ? 1 : 0);
        $losses = $stat->losses + ($fullPnl < 0 ? 1 : 0);

        $stat->forceFill([
            'profit' => (float) $stat->profit + $profitLoss,
            'fees' => (float) $stat->fees + $fees,
            'total_trades' => $totalTrades,
            'wins' => $wins,
            'losses' => $losses,
            'winrate' => $totalTrades > 0 ? round(($wins / $totalTrades) * 100, 2) : 0,
        ])->save();
    }

    private function dateKey(?CarbonInterface $at = null): string
    {
        return $this->tradingDay($at)->toDateString();
    }
}
