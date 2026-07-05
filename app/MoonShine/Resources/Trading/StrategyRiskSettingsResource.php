<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Trading;

use App\Models\StrategyRiskSettings;
use App\MoonShine\Resources\Trading\StrategyResource;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Switcher;

class StrategyRiskSettingsResource extends ModelResource
{
    protected string $model = StrategyRiskSettings::class;

    protected string $title = 'Risk Settings';

    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Strategy', 'strategy', resource: StrategyResource::class),
            Number::make('SL Multiplier', 'sl_multiplier', step: '0.01'),
            Number::make('TP Multiplier', 'tp_multiplier', step: '0.01'),
            Number::make('Risk fraction', 'max_risk_per_trade', step: '0.001'),
            Number::make('Max Positions', 'max_positions'),
            Switcher::make('Daily Target Enabled', 'daily_target_enabled'),
            Number::make('Daily Profit Target %', 'daily_profit_target_pct', step: '0.01'),
        ];
    }

    protected function formFields(): iterable
    {
        return [
            ID::make()->sortable(),

            BelongsTo::make('Strategy', 'strategy', resource: StrategyResource::class)
                ->required(),

            Number::make('SL Multiplier', 'sl_multiplier')
                ->default(2.0)
                ->step(0.01)
                ->required(),

            Number::make('TP Multiplier', 'tp_multiplier')
                ->default(3.0)
                ->step(0.01)
                ->required(),

            Number::make('Trailing Pct', 'trailing_pct')
                ->default(1.5)
                ->step(0.01)
                ->required(),

            Number::make('Max Positions', 'max_positions')
                ->default(3)
                ->min(1)
                ->required(),

            Number::make('Risk fraction', 'max_risk_per_trade')
                ->default(0.02)
                ->step(0.001)
                ->hint('Доля баланса на сделку (0.02 = 2%)')
                ->required(),

            Switcher::make('Daily Target Enabled', 'daily_target_enabled')
                ->default(true),

            Number::make('Daily Profit Target %', 'daily_profit_target_pct')
                ->default(2.30)
                ->step(0.01)
                ->required(),

            Number::make('Spot fee rate', 'spot_fee_rate')
                ->default(0.001)
                ->step(0.0001)
                ->hint('Комиссия spot за сторону (0.001 = 0.1%)')
                ->required(),

            Number::make('Min order USDT', 'min_order_usdt')
                ->default(5)
                ->step(0.01)
                ->required(),

            Number::make('Max balance pct', 'max_balance_pct')
                ->default(0.30)
                ->step(0.01)
                ->hint('Макс. доля баланса на одну сделку (0.30 = 30%)')
                ->required(),

            Number::make('Free balance buffer', 'free_balance_buffer')
                ->default(0.98)
                ->step(0.01)
                ->hint('Запас от свободного USDT (0.98 = 98%)')
                ->required(),

            Number::make('Scanner cache TTL (sec)', 'scanner_cache_ttl')
                ->default(7200)
                ->min(60)
                ->hint('Интервал обновления TOP-30 (7200 = 2 часа)')
                ->required(),
        ];
    }

    protected function detailFields(): iterable
    {
        return [
            ID::make(),
            BelongsTo::make('Strategy', 'strategy', resource: StrategyResource::class),
            Number::make('SL Multiplier', 'sl_multiplier'),
            Number::make('TP Multiplier', 'tp_multiplier'),
            Number::make('Trailing Pct', 'trailing_pct'),
            Number::make('Max Positions', 'max_positions'),
            Number::make('Risk fraction', 'max_risk_per_trade'),
            Switcher::make('Daily Target Enabled', 'daily_target_enabled'),
            Number::make('Daily Profit Target %', 'daily_profit_target_pct'),
            Number::make('Spot fee rate', 'spot_fee_rate'),
            Number::make('Min order USDT', 'min_order_usdt'),
            Number::make('Max balance pct', 'max_balance_pct'),
            Number::make('Free balance buffer', 'free_balance_buffer'),
            Number::make('Scanner cache TTL (sec)', 'scanner_cache_ttl'),
        ];
    }
}