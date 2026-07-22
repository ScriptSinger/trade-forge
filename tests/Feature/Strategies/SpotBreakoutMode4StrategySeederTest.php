<?php

declare(strict_types=1);

namespace Tests\Feature\Strategies;

use App\Models\Bot;
use App\Models\Strategy;
use App\Models\StrategyBtcTrendFilter;
use App\Models\StrategyEntrySettings;
use App\Models\StrategyRiskSettings;
use Database\Seeders\Strategies\SpotBreakoutMode4StrategySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SpotBreakoutMode4StrategySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_sample_aza_trade_preset_exactly(): void
    {
        Artisan::call('db:seed', ['--class' => SpotBreakoutMode4StrategySeeder::class]);

        $strategy = Strategy::query()
            ->where('name', 'smart-hybrid-v1.0.0')
            ->firstOrFail();

        $this->assertSame(SpotBreakoutMode4StrategySeeder::STRATEGY_NAME, $strategy->name);

        $entry = StrategyEntrySettings::query()->where('strategy_id', $strategy->id)->firstOrFail();
        $risk = StrategyRiskSettings::query()->where('strategy_id', $strategy->id)->firstOrFail();
        $btc = StrategyBtcTrendFilter::query()->where('strategy_id', $strategy->id)->firstOrFail();

        // sample/aza_trade.py + config_pro.py + core_math.py
        $this->assertSame(4, $entry->strategy_mode);
        $this->assertSame(15, $entry->interval);
        $this->assertSame(20, $entry->period);
        $this->assertSame(1000, $entry->kline_limit);
        $this->assertSame(50, $entry->ema_fast);
        $this->assertSame(200, $entry->ema_slow);
        $this->assertSame(25.0, $entry->adx_min);
        $this->assertSame(30, $entry->trend_adx_threshold);
        $this->assertSame(55.0, $entry->rsi_limit_sniper);
        $this->assertSame(75.0, $entry->rsi_limit_hybrid);

        $this->assertSame(2.0, $risk->sl_multiplier);
        $this->assertSame(3.0, $risk->tp_multiplier);
        $this->assertSame(1.5, $risk->trailing_pct);
        $this->assertSame(0.5, $risk->hybrid_tp_portion);
        $this->assertSame(1.0025, $risk->hybrid_be_multiplier);
        $this->assertSame(3, $risk->max_positions);
        $this->assertSame(0.02, $risk->max_risk_per_trade);
        $this->assertTrue($risk->daily_target_enabled);
        $this->assertSame(2.30, $risk->daily_profit_target_pct);
        $this->assertSame(0.001, $risk->spot_fee_rate);
        $this->assertSame(7200, $risk->scanner_cache_ttl);

        $this->assertTrue($btc->enabled);
        $this->assertSame('BTCUSDT', $btc->benchmark_symbol);
        $this->assertSame(60, $btc->benchmark_interval);
        $this->assertSame(50, $btc->ema_fast);
        $this->assertSame(200, $btc->ema_slow);

        $this->assertSame(0, Bot::query()->count());
    }

    public function test_seeder_is_idempotent(): void
    {
        Artisan::call('db:seed', ['--class' => SpotBreakoutMode4StrategySeeder::class]);
        Artisan::call('db:seed', ['--class' => SpotBreakoutMode4StrategySeeder::class]);

        $this->assertDatabaseCount('strategies', 1);
        $this->assertDatabaseCount('strategy_entry_settings', 1);
        $this->assertDatabaseCount('strategy_risk_settings', 1);
        $this->assertDatabaseCount('strategy_btc_trend_filters', 1);
    }
}
