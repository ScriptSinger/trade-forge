<?php

declare(strict_types=1);

namespace App\Services\Bot\Performance;

use App\Models\Bot;
use App\Models\BotStat;
use App\Services\Bot\Engine\TradingLogger;
use App\Services\Notifications\TelegramNotifier;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use MoonShine\Laravel\Notifications\MoonShineNotification;
use MoonShine\Support\Enums\Color;

class ZReportService
{
    public function __construct(
        private TelegramNotifier $telegram,
        private DailyPerformanceService $dailyPerformance,
        private TradingLogger $log,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('trading.z_report.enabled', true);
    }

    public function timezone(): string
    {
        return (string) config('trading.z_report.timezone', config('app.timezone'));
    }

    public function isDue(?CarbonInterface $at = null): bool
    {
        $moment = $this->moment($at);
        [$hour, $minute] = $this->scheduledTime();

        return $moment->hour > $hour
            || ($moment->hour === $hour && $moment->minute >= $minute);
    }

    /**
     * Calendar day covered by the report when the job runs after the scheduled time.
     */
    public function reportDate(?CarbonInterface $at = null): CarbonInterface
    {
        return $this->moment($at)->copy()->subDay()->startOfDay();
    }

    public function alreadySent(Bot $bot, ?CarbonInterface $at = null): bool
    {
        return Cache::has($this->cacheKey($bot, $at));
    }

    public function sendForBot(Bot $bot, ?CarbonInterface $at = null, bool $force = false): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $moment = $this->moment($at);

        if (! $force && ! $this->isDue($moment)) {
            $this->log->botDebug('Z-report skipped: before scheduled time', [
                'bot_id' => $bot->id,
                'now' => $moment->toDateTimeString(),
            ]);

            return false;
        }

        if (! $force && $this->alreadySent($bot, $moment)) {
            $this->log->botDebug('Z-report skipped: already sent', [
                'bot_id' => $bot->id,
            ]);

            return false;
        }

        $stat = $this->statForReport($bot, $moment);
        $lines = $this->reportLines($bot, $stat);

        $sentTelegram = $this->telegram->isConfigured()
            && $this->telegram->send($this->buildTelegramMessage($bot, $stat));
        $sentMoonshine = false;

        if ((bool) config('trading.z_report.moonshine_notify', true)) {
            MoonShineNotification::send(
                message: $this->formatMoonShineMessage($lines),
                color: Color::INFO,
            );
            $sentMoonshine = true;
        }

        if (! $sentTelegram && ! $sentMoonshine) {
            $this->log->botWarning('Z-report not delivered: Telegram is not configured and MoonShine notify disabled', [
                'bot_id' => $bot->id,
            ]);

            return false;
        }

        if ($this->telegram->isConfigured() && ! $sentTelegram) {
            $this->log->botWarning('Z-report Telegram delivery failed', [
                'bot_id' => $bot->id,
            ]);
        }

        $this->markSent($bot, $moment);
        $this->dailyPerformance->refreshStartBalance($bot);

        $this->log->botInfo('Z-report sent', [
            'bot_id' => $bot->id,
            'bot_name' => $bot->name,
            'report_date' => $stat->date->toDateString(),
            'total_trades' => $stat->total_trades,
            'profit' => (float) $stat->profit,
            'telegram' => $sentTelegram,
            'moonshine' => $sentMoonshine,
        ]);

        return true;
    }

    /**
     * @return list<string>
     */
    public function reportLines(Bot $bot, BotStat $stat): array
    {
        $winrate = $stat->total_trades > 0
            ? round((float) $stat->winrate, 1)
            : 0.0;

        return [
            '📊 Z-ОТЧЕТ',
            'Бот: '.$bot->name,
            'Дата: '.$stat->date->format('d.m.Y'),
            'Сделок: '.$stat->total_trades,
            'Winrate: '.$winrate.'%',
            'Комиссии биржи: -'.$this->formatUsdt((float) $stat->fees).' USDT',
            'ЧИСТЫЙ ПРОФИТ: '.$this->formatUsdt((float) $stat->profit).' USDT',
        ];
    }

    /**
     * @param  list<string>  $lines
     */
    public function formatMoonShineMessage(array $lines): string
    {
        return implode("\n", $lines);
    }

    public function buildTelegramMessage(Bot $bot, BotStat $stat): string
    {
        $winrate = $stat->total_trades > 0
            ? round((float) $stat->winrate, 1)
            : 0.0;

        return implode("\n", [
            '📊 <b>Z-ОТЧЕТ</b>',
            '<b>Бот:</b> '.e($bot->name),
            '<b>Дата:</b> '.$stat->date->format('d.m.Y'),
            'Сделок: '.$stat->total_trades,
            'Winrate: '.$winrate.'%',
            'Комиссии биржи: -'.$this->formatUsdt((float) $stat->fees).' USDT',
            '<b>ЧИСТЫЙ ПРОФИТ: '.$this->formatUsdt((float) $stat->profit).' USDT</b>',
        ]);
    }

    public function buildMessage(Bot $bot, BotStat $stat): string
    {
        return $this->buildTelegramMessage($bot, $stat);
    }

    private function statForReport(Bot $bot, CarbonInterface $moment): BotStat
    {
        $date = $this->reportDate($moment)->toDateString();

        $stat = BotStat::query()
            ->where('bot_id', $bot->id)
            ->whereDate('date', $date)
            ->first();

        if ($stat) {
            return $stat;
        }

        return BotStat::query()->create([
            'bot_id' => $bot->id,
            'date' => $date,
            'profit' => 0,
            'fees' => 0,
            'total_trades' => 0,
            'wins' => 0,
            'losses' => 0,
            'winrate' => 0,
        ]);
    }

    private function markSent(Bot $bot, CarbonInterface $at): void
    {
        Cache::put($this->cacheKey($bot, $at), true, $this->moment($at)->copy()->endOfDay());
    }

    private function cacheKey(Bot $bot, ?CarbonInterface $at): string
    {
        $sendDay = $this->moment($at)->toDateString();

        return "z_report:sent:{$bot->id}:{$sendDay}";
    }

    private function moment(?CarbonInterface $at): CarbonInterface
    {
        return $at
            ? Carbon::parse($at)->timezone($this->timezone())
            : now()->timezone($this->timezone());
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function scheduledTime(): array
    {
        $parts = explode(':', (string) config('trading.z_report.time', '05:05'));

        return [(int) ($parts[0] ?? 5), (int) ($parts[1] ?? 5)];
    }

    private function formatUsdt(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
