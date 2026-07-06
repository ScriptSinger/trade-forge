<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BotStatus;
use App\Enums\TelegramControlButton;
use App\Models\Bot;
use App\Models\ExchangeAccount;
use App\Models\Strategy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'test-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'trading.telegram.bot_token' => 'test-token',
            'trading.telegram.chat_id' => '401745318',
            'trading.telegram.control_enabled' => true,
            'trading.telegram.control_mode' => 'webhook',
            'telegram.bots.mybot.webhook_secret' => self::WEBHOOK_SECRET,
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
        ]);
    }

    public function test_webhook_activates_bot_on_start_button(): void
    {
        $bot = $this->createBot(BotStatus::Paused);

        $response = $this->postJson('/telegram/webhook', $this->updatePayload(TelegramControlButton::StartBots->value), [
            'X-Telegram-Bot-Api-Secret-Token' => self::WEBHOOK_SECRET,
        ]);

        $response->assertOk();
        $this->assertSame(BotStatus::Active, $bot->fresh()->status);
    }

    public function test_webhook_rejects_invalid_secret(): void
    {
        $bot = $this->createBot(BotStatus::Paused);

        $response = $this->postJson('/telegram/webhook', $this->updatePayload(TelegramControlButton::StartBots->value), [
            'X-Telegram-Bot-Api-Secret-Token' => 'wrong-secret',
        ]);

        $response->assertForbidden();
        $this->assertSame(BotStatus::Paused, $bot->fresh()->status);
    }

    public function test_webhook_ignores_other_chats(): void
    {
        $bot = $this->createBot(BotStatus::Paused);

        $response = $this->postJson(
            '/telegram/webhook',
            $this->updatePayload(TelegramControlButton::StartBots->value, chatId: '999999'),
            ['X-Telegram-Bot-Api-Secret-Token' => self::WEBHOOK_SECRET],
        );

        $response->assertOk();
        $this->assertSame(BotStatus::Paused, $bot->fresh()->status);
    }

    public function test_webhook_returns_ok_when_control_mode_off(): void
    {
        config(['trading.telegram.control_mode' => 'off']);

        $bot = $this->createBot(BotStatus::Paused);

        $response = $this->postJson('/telegram/webhook', $this->updatePayload(TelegramControlButton::StartBots->value), [
            'X-Telegram-Bot-Api-Secret-Token' => self::WEBHOOK_SECRET,
        ]);

        $response->assertOk();
        $this->assertSame(BotStatus::Paused, $bot->fresh()->status);
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
    private function updatePayload(string $text, string $chatId = '401745318'): array
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