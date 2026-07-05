<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Trading;

use App\Models\StrategyRiskSettings;
use App\MoonShine\Resources\Trading\StrategyResource;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\MenuManager\Attributes\SkipMenu;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Switcher;

#[SkipMenu]
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
                ->hint('Стоп-лосс: entry − ATR × множитель (SL_MULT = 2.0 в sample)')
                ->required(),

            Number::make('TP Multiplier', 'tp_multiplier')
                ->default(3.0)
                ->step(0.01)
                ->hint('Тейк-профит: entry + ATR × множитель (TP_MULT = 3 в sample)')
                ->required(),

            Number::make('Trailing Pct', 'trailing_pct')
                ->default(1.5)
                ->step(0.01)
                ->hint('Отступ трейлинг-стопа от пика цены в % (TRAILING_STEP 0.985 = 1.5% в sample)')
                ->required(),

            Number::make('Max Positions', 'max_positions')
                ->default(3)
                ->min(1)
                ->hint('Макс. одновременных открытых позиций (MAX_POSITIONS = 3 в sample)')
                ->required(),

            Number::make('Risk fraction', 'max_risk_per_trade')
                ->default(0.02)
                ->step(0.001)
                ->hint('Доля баланса на расчёт риска в USDT (RISK = 0.02 в sample)')
                ->required(),

            Switcher::make('Daily Target Enabled', 'daily_target_enabled')
                ->default(true)
                ->hint('После достижения дневной цели включает BTC guard и ужесточает выходы'),

            Number::make('Daily Profit Target %', 'daily_profit_target_pct')
                ->default(2.30)
                ->step(0.01)
                ->hint('Дневная цель чистой прибыли в % от стартового баланса (DAILY_PROFIT_TARGET_PCT = 2.3 в sample)')
                ->required(),

            Number::make('Spot fee rate', 'spot_fee_rate')
                ->default(0.001)
                ->step(0.0001)
                ->readonly()
                ->hint('Комиссия Bybit spot за сторону, по умолчанию 0.001 (0.1%), не параметр стратегии'),

            Number::make('Min order USDT', 'min_order_usdt')
                ->default(5)
                ->step(0.01)
                ->readonly()
                ->hint('Минимум ордера Bybit spot, по умолчанию 5 USDT, не параметр стратегии'),

            Number::make('Max balance pct', 'max_balance_pct')
                ->default(0.30)
                ->step(0.01)
                ->readonly()
                ->hint('Макс. доля баланса на одну сделку, по умолчанию 0.30 (30%), не параметр стратегии'),

            Number::make('Free balance buffer', 'free_balance_buffer')
                ->default(0.98)
                ->step(0.01)
                ->readonly()
                ->hint('Запас от свободного USDT при сайзинге, по умолчанию 0.98 (98%), не параметр стратегии'),

            Number::make('Scanner cache TTL (sec)', 'scanner_cache_ttl')
                ->default(7200)
                ->min(60)
                ->readonly()
                ->hint('Интервал обновления TOP-30, по умолчанию 7200 сек (2 ч), не параметр стратегии'),
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