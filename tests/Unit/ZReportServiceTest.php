<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\BotStatus;
use App\Models\Bot;
use App\Models\BotStat;
use App\Models\ExchangeAccount;
use App\Models\Strategy;
use App\Models\User;
use App\Services\Bot\Engine\TradingLogger;
use App\Services\Bot\Performance\DailyPerformanceService;
use App\Services\Bot\Performance\ZReportService;
use App\Services\Exchange\Balance\AccountBalanceSnapshot;
use App\Services\Exchange\Bybit\BybitExchangeService;
use App\Services\Notifications\TelegramNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class ZReportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_build_message_matches_sample_format(): void
    {
        $bot = $this->makeBot('Bybit Spot Bot');

        $stat = BotStat::query()->create([
            'bot_id' => $bot->id,
            'date' => '2026-07-05',
            'total_trades' => 4,
            'wins' => 3,
            'losses' => 1,
            'winrate' => 75,
            'profit' => 18.42,
            'fees' => 1.23,
        ]);

        $service = $this->makeService();

        $lines = $service->reportLines($bot, $stat);
        $telegram = $service->buildTelegramMessage($bot, $stat);
        $moonshine = $service->formatMoonShineMessage($lines);

        $this->assertStringContainsString('Z-ОТЧЕТ', $telegram);
        $this->assertStringContainsString('Сделок: 4', $telegram);
        $this->assertStringContainsString("Сделок: 4\nWinrate: 75%", $moonshine);
        $this->assertStringContainsString('Комиссии биржи: -1.23 USDT', $moonshine);
        $this->assertStringContainsString('ЧИСТЫЙ ПРОФИТ: 18.42 USDT', $moonshine);
    }

    public function test_skips_before_scheduled_time_without_force(): void
    {
        config([
            'trading.z_report.time' => '05:05',
            'trading.z_report.timezone' => 'Asia/Yekaterinburg',
        ]);

        Http::fake();

        $bot = $this->makeBot();
        $service = $this->makeService();

        $at = Carbon::parse('2026-07-06 04:30:00', 'Asia/Yekaterinburg');

        $this->assertFalse($service->sendForBot($bot, $at));
        Http::assertNothingSent();
    }

    public function test_sends_via_telegram_after_scheduled_time(): void
    {
        config([
            'trading.telegram.bot_token' => 'test-token',
            'trading.telegram.chat_id' => '12345',
            'trading.z_report.time' => '05:05',
            'trading.z_report.timezone' => 'Asia/Yekaterinburg',
        ]);

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $bot = $this->makeBot();

        BotStat::query()->create([
            'bot_id' => $bot->id,
            'date' => '2026-07-05',
            'total_trades' => 2,
            'wins' => 1,
            'losses' => 1,
            'winrate' => 50,
            'profit' => 5,
            'fees' => 0.5,
        ]);

        $exchange = Mockery::mock(BybitExchangeService::class);
        $exchange->shouldReceive('getUsdtBalance')->twice()->andReturn(
            new AccountBalanceSnapshot('USDT', 1000.0, 1000.0, 0.0, 'walletBalance_minus_locked'),
        );

        $logger = Mockery::mock(TradingLogger::class);
        $logger->shouldIgnoreMissing();

        $service = new ZReportService(
            new TelegramNotifier,
            new DailyPerformanceService($exchange, $logger),
            $logger,
        );

        $at = Carbon::parse('2026-07-06 05:10:00', 'Asia/Yekaterinburg');
        Carbon::setTestNow($at);

        try {
            $this->assertTrue($service->sendForBot($bot, $at));
            $this->assertTrue($service->alreadySent($bot, $at));
        } finally {
            Carbon::setTestNow();
        }

        Http::assertSentCount(1);
    }

    private function makeService(): ZReportService
    {
        $logger = Mockery::mock(TradingLogger::class);
        $logger->shouldIgnoreMissing();

        return new ZReportService(
            new TelegramNotifier,
            new DailyPerformanceService(
                Mockery::mock(BybitExchangeService::class),
                $logger,
            ),
            $logger,
        );
    }

    private function makeBot(string $name = 'Test Bot'): Bot
    {
        Cache::flush();

        $user = User::factory()->create();
        $strategy = Strategy::query()->create([
            'name' => 'Test Strategy',
            'is_active' => true,
        ]);
        $exchangeAccount = ExchangeAccount::factory()->for($user)->create();

        return Bot::factory()->for($user)->create([
            'name' => $name,
            'status' => BotStatus::Active,
            'exchange_account_id' => $exchangeAccount->id,
            'strategy_id' => $strategy->id,
        ]);
    }
}
