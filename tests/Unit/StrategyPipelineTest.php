<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\StrategyType;
use App\Enums\TradeContextStatus;
use App\Enums\TradeSignal;
use App\Models\Bot;
use App\Models\ExchangeAccount;
use App\Models\Strategy;
use App\Models\StrategyEntrySettings;
use App\Models\StrategyRiskSettings;
use App\Models\User;
use App\Services\Bot\PositionSizingService;
use App\Services\Bot\Strategy\Pipes\ApplyRiskManagement;
use App\Services\Bot\Strategy\Pipes\CheckBreakoutLevel;
use App\Services\Bot\Strategy\Pipes\DetermineStrategyMode;
use App\Services\Bot\Strategy\Pipes\ValidateStrategySettings;
use App\Services\Bot\Strategy\TradeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class StrategyPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_validate_strategy_settings_blocks_without_entry_settings(): void
    {
        $context = $this->makeContext(withEntry: false, withRisk: true);

        $result = (new ValidateStrategySettings())->handle($context, fn ($ctx) => $ctx);

        $this->assertTrue($result->isBlocked);
        $this->assertSame('Missing strategy entry settings', $result->reason);
    }

    public function test_check_breakout_blocks_when_close_not_above_prev_resistance(): void
    {
        $context = $this->makeContext();
        $context->candles = [['ts' => 1, 'open' => 100, 'high' => 101, 'low' => 99, 'close' => 100, 'vol' => 10]];
        $context->indicators['prev_resistance'] = 105;

        $result = (new CheckBreakoutLevel())->handle($context, fn ($ctx) => $ctx);

        $this->assertTrue($result->isBlocked);
        $this->assertStringContainsString('No breakout', $result->reason);
    }

    public function test_determine_strategy_mode_sets_buy_signal_for_sniper(): void
    {
        $context = $this->makeContext();
        $context->indicators['adx'] = [22, 24];
        $context->indicators['rsi'] = [40, 48];

        $result = (new DetermineStrategyMode())->handle($context, fn ($ctx) => $ctx);

        $this->assertFalse($result->isBlocked);
        $this->assertSame('Sniper', $result->mode);
        $this->assertSame(TradeSignal::Buy, $result->signal);
    }

    public function test_apply_risk_management_sets_base_asset_quantity(): void
    {
        $context = $this->makeContext();
        $context->candles = [['ts' => 1, 'open' => 100, 'high' => 102, 'low' => 98, 'close' => 100, 'vol' => 10]];
        $context->indicators['atr'] = [0, 2];

        $sizing = Mockery::mock(PositionSizingService::class);
        $sizing->shouldReceive('calculateQuantity')->once()->andReturn(0.25);

        $result = (new ApplyRiskManagement($sizing))->handle($context, fn ($ctx) => $ctx);

        $this->assertFalse($result->isBlocked);
        $this->assertEqualsWithDelta(96.0, $result->stopLoss, 0.001);
        $this->assertEqualsWithDelta(106.0, $result->takeProfit, 0.001);
        $this->assertEqualsWithDelta(0.25, $result->quantity, 0.001);
    }

    private function makeContext(bool $withEntry = true, bool $withRisk = true): TradeContext
    {
        $user = User::factory()->create();
        $strategy = Strategy::query()->create([
            'name' => 'Pipeline Strategy',
            'type' => StrategyType::Hybrid->value,
            'is_active' => true,
        ]);

        if ($withEntry) {
            StrategyEntrySettings::query()->create([
                'strategy_id' => $strategy->id,
                'interval' => 15,
                'period' => 20,
                'ema_fast' => 50,
                'ema_slow' => 200,
                'adx_min' => 25,
                'trend_adx_threshold' => 30,
                'rsi_limit_sniper' => 55,
                'rsi_limit_hybrid' => 75,
            ]);
        }

        if ($withRisk) {
            StrategyRiskSettings::query()->create([
                'strategy_id' => $strategy->id,
                'sl_multiplier' => 2.0,
                'tp_multiplier' => 3.0,
                'trailing_pct' => 1.5,
                'max_positions' => 3,
                'max_risk_per_trade' => 0.02,
            ]);
        }

        $exchangeAccount = ExchangeAccount::factory()->for($user)->create();
        $bot = Bot::factory()->for($user)->create([
            'exchange_account_id' => $exchangeAccount->id,
            'strategy_id' => $strategy->id,
        ]);

        $bot->loadMissing(['strategy.entrySettings', 'strategy.riskSettings']);

        return new TradeContext(
            bot: $bot,
            symbol: 'BTCUSDT',
            candles: [['1', '100', '101', '99', '100', '10']],
        );
    }
}