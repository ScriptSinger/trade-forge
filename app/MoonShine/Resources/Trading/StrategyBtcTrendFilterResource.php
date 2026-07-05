<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Trading;

use App\Models\StrategyBtcTrendFilter;
use App\MoonShine\Resources\Trading\StrategyResource;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\MenuManager\Attributes\SkipMenu;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

#[SkipMenu]
class StrategyBtcTrendFilterResource extends ModelResource
{
    protected string $model = StrategyBtcTrendFilter::class;

    protected string $title = 'BTC Filter (Daily Target)';

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
                ->default(true)
                ->hint('Используется только после достижения дневной цели прибыли'),

            Text::make('Symbol', 'benchmark_symbol')
                ->default('BTCUSDT')
                ->readonly()
                ->hint('Бенчмарк для дневного guard, по умолчанию BTCUSDT, не параметр стратегии'),

            Select::make('Interval', 'benchmark_interval')
                ->options([
                    '1' => '1m',
                    '3' => '3m',
                    '5' => '5m',
                    '15' => '15m',
                    '60' => '1h',
                ])
                ->default('60')
                ->readonly()
                ->hint('Таймфрейм BTC-фильтра, по умолчанию 1h (GLOBAL_TF в sample), не параметр стратегии'),

            Number::make('EMA Fast', 'ema_fast')
                ->default(50)
                ->min(1)
                ->hint('Быстрая EMA BTC — аптренд, если EMA fast > EMA slow (как btc_ok() в sample)'),

            Number::make('EMA Slow', 'ema_slow')
                ->default(200)
                ->min(1)
                ->hint('Медленная EMA BTC для сравнения тренда (span 200 в sample)'),
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
