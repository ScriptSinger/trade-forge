<?php

namespace App\Services\Bot;

use App\Models\Bot;
use App\Models\BotStat;
use App\Models\Trade;
use App\Services\Exchange\BybitExchangeService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class DailyPerformanceService
{
    public function __construct(
        private BybitExchangeService $exchange,
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

        if (!$stat) {
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

        $balance = $this->exchange->getUsdtWalletBalance($bot->exchangeAccount);

        if ($balance === null || $balance <= 0) {
            Log::channel('bot')->warning('DailyPerformance: could not capture start_balance', [
                'bot_id' => $bot->id,
            ]);

            return $stat;
        }

        $stat->forceFill([
            'start_balance' => $balance,
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

    public function recordClosedTrade(Trade $trade): void
    {
        $dateKey = $this->dateKey($trade->closed_at);

        $stat = BotStat::query()
            ->where('bot_id', $trade->bot_id)
            ->whereDate('date', $dateKey)
            ->first();

        if (!$stat) {
            $stat = BotStat::query()->create([
                'bot_id' => $trade->bot_id,
                'date' => $dateKey,
                'profit' => 0,
                'fees' => 0,
            ]);
        }

        $profitLoss = (float) $trade->profit_loss;
        $fees = (float) ($trade->fees ?? 0);

        $totalTrades = $stat->total_trades + 1;
        $wins = $stat->wins + ($profitLoss >= 0 ? 1 : 0);
        $losses = $stat->losses + ($profitLoss < 0 ? 1 : 0);

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