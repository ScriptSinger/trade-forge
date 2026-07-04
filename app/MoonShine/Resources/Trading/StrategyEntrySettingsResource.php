<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Trading;

use App\Models\StrategyEntrySettings;
use App\MoonShine\Resources\Trading\StrategyResource;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Select;

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
                ->default(20),
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
        ];
    }
}
