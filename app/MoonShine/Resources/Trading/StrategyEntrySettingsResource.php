<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Trading;

use App\Models\StrategyEntrySettings;
use App\MoonShine\Resources\Trading\StrategyResource;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\MenuManager\Attributes\SkipMenu;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Select;

#[SkipMenu]
class StrategyEntrySettingsResource extends ModelResource
{
    protected string $model = StrategyEntrySettings::class;

    protected string $title = 'Entry Settings';

    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),

            Select::make('Interval', 'interval'),

            Number::make('Period', 'period'),
            Number::make('EMA Fast', 'ema_fast'),
            Number::make('EMA Slow', 'ema_slow'),
            Number::make('Min ADX', 'adx_min'),
            Number::make('Trend ADX Threshold', 'trend_adx_threshold'),
            Number::make('RSI Limit Sniper', 'rsi_limit_sniper'),
            Number::make('RSI Limit Hybrid', 'rsi_limit_hybrid'),
        ];
    }

    protected function formFields(): iterable
    {
        return [
            ID::make()->sortable(),

            BelongsTo::make('Strategy', 'strategy', resource: StrategyResource::class)
                ->required(),

            Select::make('Interval', 'interval')
                ->options([
                    '1' => '1m',
                    '3' => '3m',
                    '5' => '5m',
                    '15' => '15m',
                    '60' => '1h',
                ])
                ->default('1'),

            Number::make('Period', 'period')
                ->default(20)
                ->min(1)
                ->required(),

            Number::make('EMA Fast', 'ema_fast')
                ->default(50)
                ->min(1),

            Number::make('EMA Slow', 'ema_slow')
                ->default(200)
                ->min(1),

            Number::make('Min ADX', 'adx_min')
                ->default(25),

            Number::make('Trend ADX Threshold', 'trend_adx_threshold')
                ->default(30)
                ->min(1),

            Number::make('RSI Limit Sniper', 'rsi_limit_sniper')
                ->default(55)
                ->step(0.01),

            Number::make('RSI Limit Hybrid', 'rsi_limit_hybrid')
                ->default(75)
                ->step(0.01),
        ];
    }

    protected function detailFields(): iterable
    {
        return [
            ID::make(),

            Select::make('Interval', 'interval'),

            Number::make('Period', 'period'),
            Number::make('EMA Fast', 'ema_fast'),
            Number::make('EMA Slow', 'ema_slow'),
            Number::make('Min ADX', 'adx_min'),
            Number::make('Trend ADX Threshold', 'trend_adx_threshold'),
            Number::make('RSI Limit Sniper', 'rsi_limit_sniper'),
            Number::make('RSI Limit Hybrid', 'rsi_limit_hybrid'),
        ];
    }
}
