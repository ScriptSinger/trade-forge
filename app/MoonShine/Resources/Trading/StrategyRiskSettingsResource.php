<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Trading;

use App\Models\StrategyRiskSettings;
use App\MoonShine\Resources\Trading\StrategyResource;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;

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
            Number::make('Trailing Pct', 'trailing_pct', step: '0.01'),
        ];
    }

    protected function formFields(): iterable
    {
        return [
            ID::make()->sortable(),

            BelongsTo::make('Strategy', 'strategy', resource: StrategyResource::class)
                ->required(),

            Number::make('SL Multiplier', 'sl_multiplier')
                ->default(1.5)
                ->step(0.01)
                ->required(),

            Number::make('TP Multiplier', 'tp_multiplier')
                ->default(3.0)
                ->step(0.01)
                ->required(),

            Number::make('Trailing Pct', 'trailing_pct')
                ->default(0.5)
                ->step(0.01)
                ->required(),

            Number::make('Max Positions', 'max_positions')
                ->default(1)
                ->min(1)
                ->required(),

            Number::make('Max Risk %', 'max_risk_per_trade')
                ->default(1.0)
                ->step(0.01)
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
            Number::make('Max Risk %', 'max_risk_per_trade'),
        ];
    }
}
