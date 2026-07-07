<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\BotStatus;
use App\Enums\TelegramControlButton;
use App\Enums\TelegramLogChannel;
use App\Models\Bot;
use App\Models\ExchangeAccount;
use App\Models\Strategy;
use App\Models\User;
use App\Services\Notifications\TelegramNotifier;
use App\Services\Telegram\TelegramControlPanelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramControlPanelServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        File::ensureDirectoryExists(storage_path('logs'));

        config([
            'trading.telegram.bot_token' => 'test-token',
            'trading.telegram.chat_id' => '401745318',
            'trading.telegram.control_enabled' => true,
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
        ]);
    }

    public function test_start_command_sends_welcome_without_changing_bot_status(): void
    {
        $bot = $this->createBot(BotStatus::Paused);

        $panel = new TelegramControlPanelService(new TelegramNotifier);
        $panel->handleUpdate($this->message('/start'));

        $this->assertSame(BotStatus::Paused, $bot->fresh()->status);

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'sendMessage')
                && str_contains($request['text'], 'Trade Forge');
        });
    }

    public function test_start_button_activates_paused_bots(): void
    {
        $bot = $this->createBot(BotStatus::Paused);

        $panel = new TelegramControlPanelService(new TelegramNotifier);
        $panel->handleUpdate($this->message(TelegramControlButton::StartBots->value));

        $this->assertSame(BotStatus::Active, $bot->fresh()->status);
    }

    public function test_stop_button_pauses_active_bots(): void
    {
        $bot = $this->createBot(BotStatus::Active);

        $panel = new TelegramControlPanelService(new TelegramNotifier);
        $panel->handleUpdate($this->message(TelegramControlButton::StopBots->value));

        $this->assertSame(BotStatus::Paused, $bot->fresh()->status);
    }

    public function test_ignores_messages_from_other_chats(): void
    {
        $bot = $this->createBot(BotStatus::Paused);

        $panel = new TelegramControlPanelService(new TelegramNotifier);
        $panel->handleUpdate($this->message(TelegramControlButton::StartBots->value, chatId: '999999'));

        $this->assertSame(BotStatus::Paused, $bot->fresh()->status);
    }

    public function test_bot_log_button_reads_bot_channel_file(): void
    {
        $logPath = storage_path('logs/bot-2099-12-31.log');
        file_put_contents($logPath, "bot-cycle-line\n");
        touch($logPath, time() + 3600);

        $panel = new TelegramControlPanelService(new TelegramNotifier);
        $panel->handleUpdate($this->message(TelegramLogChannel::Bot->value));

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'sendMessage')
                && str_contains($request['text'], 'bot-cycle-line')
                && str_contains($request['text'], 'bot');
        });

        @unlink($logPath);
    }

    public function test_exchange_log_button_reads_exchange_channel_file(): void
    {
        $logPath = storage_path('logs/exchange-2099-12-31.log');
        file_put_contents($logPath, "exchange-request-line\n");
        touch($logPath, time() + 3600);

        $panel = new TelegramControlPanelService(new TelegramNotifier);
        $panel->handleUpdate($this->message(TelegramLogChannel::Exchange->value));

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'sendMessage')
                && str_contains($request['text'], 'exchange-request-line')
                && str_contains($request['text'], 'exchange');
        });

        @unlink($logPath);
    }

    private function createBot(BotStatus $status): Bot
    {
        $user = User::factory()->create();
        $strategy = Strategy::query()->create(['name' => 'Test Strategy', 'is_active' => true]);
        $account = ExchangeAccount::query()->create([
            'user_id' => $user->id,
            'exchange' => 'bybit',
            'name' => 'Test Account',
            'api_key' => 'key',
            'api_secret' => 'secret',
            'api_url' => 'https://api-testnet.bybit.com',
            'status' => 'active',
        ]);

        return Bot::query()->create([
            'user_id' => $user->id,
            'exchange_account_id' => $account->id,
            'strategy_id' => $strategy->id,
            'name' => 'Test Bot',
            'status' => $status,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function message(string $text, string $chatId = '401745318'): array
    {
        return [
            'update_id' => 1,
            'message' => [
                'message_id' => 1,
                'chat' => ['id' => $chatId],
                'text' => $text,
            ],
        ];
    }
}
