<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use App\Enums\BotStatus;
use App\Enums\PositionStatus;
use App\Enums\TelegramControlButton;
use App\Enums\TelegramLogChannel;
use App\Models\Bot;
use App\Models\Position;
use App\Services\Notifications\TelegramNotifier;

class TelegramControlPanelService
{
    private const LOG_TAIL_LINES = 100;

    private const TELEGRAM_MESSAGE_LIMIT = 3800;

    public function __construct(
        private TelegramNotifier $telegram,
    ) {}

    public function isEnabled(): bool
    {
        if (config('trading.telegram.control_mode', 'webhook') === 'off') {
            return false;
        }

        return (bool) config('trading.telegram.control_enabled', true)
            && $this->telegram->isConfigured();
    }

    /**
     * @param  array<string, mixed>  $update
     */
    public function handleUpdate(array $update): void
    {
        $message = $update['message'] ?? null;

        if (! is_array($message)) {
            return;
        }

        $chatId = $message['chat']['id'] ?? null;
        $text = trim((string) ($message['text'] ?? ''));

        if ($chatId === null || $text === '') {
            return;
        }

        if (! $this->telegram->isAuthorizedChat($chatId)) {
            return;
        }

        if (str_starts_with($text, '/start')) {
            $this->sendWelcome($chatId);

            return;
        }

        $logChannel = TelegramLogChannel::tryFrom($text);

        if ($logChannel !== null) {
            $this->telegram->sendTo(
                $chatId,
                $this->buildLogTailMessage($logChannel),
                $this->menuKeyboard(),
            );

            return;
        }

        $button = TelegramControlButton::tryFrom($text);

        if ($button === null) {
            $this->telegram->sendTo(
                $chatId,
                'Неизвестная команда. Нажмите /start для меню.',
                $this->menuKeyboard(),
            );

            return;
        }

        $reply = match ($button) {
            TelegramControlButton::StartBots => $this->startBots(),
            TelegramControlButton::StopBots => $this->stopBots(),
            TelegramControlButton::Status => $this->buildStatusMessage(),
        };

        $this->telegram->sendTo($chatId, $reply, $this->menuKeyboard());
    }

    private function sendWelcome(int|string $chatId): void
    {
        $this->telegram->sendTo(
            $chatId,
            "🎛 <b>Trade Forge</b>\n\n"
            ."▶️ ⏹ — включить / поставить ботов на паузу\n"
            ."📊 — сводка по ботам и позициям\n"
            ."🤖 🔌 📈 📦 🛡 — последние 100 строк лога канала\n\n"
            .'Цикл торговли: каждые '
            .config('trading.bot.cycle_interval_seconds', 15).' сек.',
            $this->menuKeyboard(),
        );
    }

    private function startBots(): string
    {
        $bots = Bot::query()
            ->where('status', BotStatus::Paused)
            ->get();

        if ($bots->isEmpty()) {
            $activeCount = Bot::query()->where('status', BotStatus::Active)->count();

            return $activeCount > 0
                ? "✅ Все боты уже активны ({$activeCount})."
                : '⚠️ Нет ботов на паузе — создайте бота в MoonShine.';
        }

        $names = [];

        foreach ($bots as $bot) {
            $bot->update(['status' => BotStatus::Active]);
            $names[] = $bot->name;
        }

        return '▶️ <b>Запущено:</b> '.count($names)."\n".implode("\n", array_map(
            static fn (string $name): string => '  • '.e($name),
            $names,
        ));
    }

    private function stopBots(): string
    {
        $bots = Bot::query()
            ->where('status', BotStatus::Active)
            ->get();

        if ($bots->isEmpty()) {
            return '💤 Активных ботов нет.';
        }

        $names = [];

        foreach ($bots as $bot) {
            $bot->update(['status' => BotStatus::Paused]);
            $names[] = $bot->name;
        }

        return '⏹ <b>На паузе:</b> '.count($names)."\n".implode("\n", array_map(
            static fn (string $name): string => '  • '.e($name),
            $names,
        ));
    }

    private function buildStatusMessage(): string
    {
        $activeBots = Bot::query()->where('status', BotStatus::Active)->get();
        $pausedBots = Bot::query()->where('status', BotStatus::Paused)->count();
        $openPositions = Position::query()->where('status', PositionStatus::Open)->count();

        $lines = [
            '<b>📊 Статус</b>',
            '',
            '🟢 Активных: '.$activeBots->count(),
            '⏸ На паузе: '.$pausedBots,
            '📂 Позиций: '.$openPositions,
            '⏱ Цикл: '.config('trading.bot.cycle_interval_seconds', 15).' сек',
        ];

        if ($activeBots->isNotEmpty()) {
            $lines[] = '';
            $lines[] = '<b>Боты:</b>';

            foreach ($activeBots as $bot) {
                $lastRun = $bot->last_run_at?->timezone(config('app.timezone'))->format('d.m H:i:s') ?? '—';
                $lines[] = '  • '.e($bot->name)." <code>{$lastRun}</code>";
            }
        } else {
            $lines[] = '';
            $lines[] = '💤 Нет активных ботов';
        }

        return implode("\n", $lines);
    }

    private function buildLogTailMessage(TelegramLogChannel $channel): string
    {
        $loggingChannel = $channel->loggingChannel();
        $path = $this->resolveChannelLogPath($loggingChannel);

        if ($path === null) {
            return '❌ Лог «'.e($channel->shortLabel()).'» не найден.';
        }

        $output = $this->tailFile($path, self::LOG_TAIL_LINES);

        if ($output === '') {
            return '📭 «'.e($channel->shortLabel()).'» — '.e(basename($path)).' пуст.';
        }

        $output = $this->truncateForTelegram($output);

        return '<b>'.e($channel->value).'</b> · '.e(basename($path))."\n<pre>".e($output).'</pre>';
    }

    /**
     * @return array<string, mixed>
     */
    private function menuKeyboard(): array
    {
        return $this->telegram->replyKeyboard(TelegramControlButton::keyboardRows());
    }

    private function resolveChannelLogPath(string $channel): ?string
    {
        $channelConfig = config("logging.channels.{$channel}");

        if (! is_array($channelConfig)) {
            return null;
        }

        $path = $channelConfig['path'] ?? null;

        if (! is_string($path) || $path === '') {
            return null;
        }

        $driver = $channelConfig['driver'] ?? 'single';

        if ($driver === 'daily') {
            $directory = dirname($path);
            $prefix = pathinfo($path, PATHINFO_FILENAME);

            return $this->latestDailyLog($directory, "{$prefix}-*.log");
        }

        return is_readable($path) ? $path : null;
    }

    private function latestDailyLog(string $directory, string $pattern): ?string
    {
        $files = glob($directory.'/'.$pattern);

        if (! is_array($files) || $files === []) {
            return null;
        }

        usort($files, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

        $latest = $files[0];

        return is_readable($latest) ? $latest : null;
    }

    private function tailFile(string $path, int $lines): string
    {
        $content = @file($path, FILE_IGNORE_NEW_LINES);

        if (! is_array($content) || $content === []) {
            return '';
        }

        return implode("\n", array_slice($content, -$lines));
    }

    private function truncateForTelegram(string $text): string
    {
        if (strlen($text) <= self::TELEGRAM_MESSAGE_LIMIT) {
            return $text;
        }

        return '...[часть скрыта]...'.substr($text, -self::TELEGRAM_MESSAGE_LIMIT);
    }
}
