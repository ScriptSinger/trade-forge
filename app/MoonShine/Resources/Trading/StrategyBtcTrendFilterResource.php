<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Trading;

use App\Models\StrategyBtcTrendFilter;
use App\MoonShine\Resources\Trading\StrategyResource;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

class StrategyBtcTrendFilterResource extends ModelResource
{
    protected string $model = StrategyBtcTrendFilter::class;

    protected string $title = 'BTC Trend Filter';

    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Strategy', 'strategy', resource: StrategyResource::class),
            Switcher::make('Enabled', 'enabled'),
            Text::make('Symbol', 'benchmark_symbol'),
        ];
    }

    protected function formFields(): iterable
    {
        return [
            ID::make()->sortable(),

            BelongsTo::make('Strategy', 'strategy', resource: StrategyResource::class)
                ->required(),

            Switcher::make('Enabled', 'enabled')
                ->default(true),

            Text::make('Symbol', 'benchmark_symbol')
                ->default('BTCUSDT')
                ->required(),

            Select::make('Interval', 'benchmark_interval')
                ->options([
                    '1' => '1m',
                    '3' => '3m',
                    '5' => '5m',
                    '15' => '15m',
                    '60' => '1h',
                ])
                ->default('1'),

            Number::make('EMA Fast', 'ema_fast')
                ->default(50)
                ->min(1),

            Number::make('EMA Slow', 'ema_slow')
                ->default(200)
                ->min(1),
        ];
    }

    protected function detailFields(): iterable
    {
        return [
            ID::make(),
            BelongsTo::make('Strategy', 'strategy', resource: StrategyResource::class),
            Switcher::make('Enabled', 'enabled'),
            Text::make('Symbol', 'benchmark_symbol'),
            Number::make('Interval', 'benchmark_interval'),
            Number::make('EMA Fast', 'ema_fast'),
            Number::make('EMA Slow', 'ema_slow'),
        ];
    }
}
