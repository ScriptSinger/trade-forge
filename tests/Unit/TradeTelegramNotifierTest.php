<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Notifications\TelegramNotifier;
use App\Services\Notifications\TradeTelegramNotifier;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TradeTelegramNotifierTest extends TestCase
{
    public function test_notify_entry_sends_telegram_message_when_configured(): void
    {
        config([
            'trading.telegram.bot_token' => 'test-token',
            'trading.telegram.chat_id' => '12345',
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $notifier = new TradeTelegramNotifier(new TelegramNotifier);
        $notifier->notifyEntry('ETHUSDT', 2500.0, 2400.0, 2700.0, 125.0);

        Http::assertSent(function ($request): bool {
            $body = $request->data();

            return str_contains((string) ($body['text'] ?? ''), 'ВХОД: ETHUSDT')
                && str_contains((string) ($body['text'] ?? ''), 'SL: 2400')
                && str_contains((string) ($body['text'] ?? ''), 'Объем: 125.00$');
        });
    }

    public function test_notify_surfer_activation_sends_rocket_message(): void
    {
        config([
            'trading.telegram.bot_token' => 'test-token',
            'trading.telegram.chat_id' => '12345',
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $notifier = new TradeTelegramNotifier(new TelegramNotifier);
        $notifier->notifySurferActivation('BTCUSDT');

        Http::assertSent(function ($request): bool {
            $text = (string) ($request->data()['text'] ?? '');

            return str_contains($text, 'Ракета!')
                && str_contains($text, 'BTCUSDT');
        });
    }

    public function test_notify_exit_uses_minus_emoji_for_partial_loss(): void
    {
        config([
            'trading.telegram.bot_token' => 'test-token',
            'trading.telegram.chat_id' => '12345',
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $notifier = new TradeTelegramNotifier(new TelegramNotifier);
        $notifier->notifyExit('ETHUSDT', 'Take Profit (50%)', 0.5, -0.5, -1.25);

        Http::assertSent(function ($request): bool {
            $text = (string) ($request->data()['text'] ?? '');

            return str_contains($text, '➖')
                && str_contains($text, 'ВЫХОД: ETHUSDT')
                && str_contains($text, '(50%)');
        });
    }
}
