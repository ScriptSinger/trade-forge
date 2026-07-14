<?php

declare(strict_types=1);

namespace Tests\Feature\Strategies;

use App\Models\Bot;
use App\Models\Strategy;
use App\Models\StrategyEntrySettings;
use App\Models\StrategyRiskSettings;
use Database\Seeders\Strategies\SpotBreakoutMode4RelaxedStrategySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SpotBreakoutMode4RelaxedStrategySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_relaxed_strategy_preset_without_bots(): void
    {
        Artisan::call('db:seed', ['--class' => SpotBreakoutMode4RelaxedStrategySeeder::class]);

        $strategy = Strategy::query()
            ->where('name', SpotBreakoutMode4RelaxedStrategySeeder::STRATEGY_NAME)
            ->firstOrFail();
        $entry = StrategyEntrySettings::query()->where('strategy_id', $strategy->id)->firstOrFail();
        $risk = StrategyRiskSettings::query()->where('strategy_id', $strategy->id)->firstOrFail();

        $this->assertSame(4, $entry->strategy_mode);
        $this->assertSame(15, $entry->interval);
        $this->assertSame(1000, $entry->kline_limit);
        $this->assertSame(20.0, $entry->adx_min);
        $this->assertSame(25, $entry->trend_adx_threshold);
        $this->assertSame(62.0, $entry->rsi_limit_sniper);
        $this->assertSame(80.0, $entry->rsi_limit_hybrid);
        $this->assertSame(3.0, $risk->tp_multiplier);
        $this->assertSame(0.02, $risk->max_risk_per_trade);
        $this->assertSame(2.30, $risk->daily_profit_target_pct);
        $this->assertSame(0, Bot::query()->count());
    }

    public function test_seeder_is_idempotent(): void
    {
        Artisan::call('db:seed', ['--class' => SpotBreakoutMode4RelaxedStrategySeeder::class]);
        Artisan::call('db:seed', ['--class' => SpotBreakoutMode4RelaxedStrategySeeder::class]);

        $this->assertDatabaseCount('strategies', 1);
        $this->assertDatabaseCount('strategy_entry_settings', 1);
        $this->assertDatabaseCount('strategy_risk_settings', 1);
        $this->assertDatabaseCount('strategy_btc_trend_filters', 1);
    }
}