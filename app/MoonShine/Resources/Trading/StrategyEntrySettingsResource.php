<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Trading;

use App\Models\StrategyEntrySettings;
use App\MoonShine\Resources\Trading\StrategyResource;
use App\Services\Exchange\BybitExchangeService;
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
            Number::make('Kline limit', 'kline_limit'),
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
                ->default('15')
                ->hint('Таймфрейм свечей для входа и мониторинга ADX (как TIMEFRAME в sample — 15m)'),

            Number::make('Period', 'period')
                ->default(20)
                ->min(1)
                ->hint('Окно для breakout и volume: max high и средний объём за последние N свечей (не глубина загрузки с биржи)')
                ->required(),

            Number::make('Kline limit', 'kline_limit')
                ->default(BybitExchangeService::DEFAULT_KLINE_LIMIT)
                ->min(200)
                ->max(1000)
                ->hint('Сколько свечей запрашивать с Bybit для EMA/ADX/RSI. Минимум ≥ EMA Slow. Для стабильной EMA200 как в sample — 1000')
                ->required(),

            Number::make('EMA Fast', 'ema_fast')
                ->default(50)
                ->min(1)
                ->hint('Быстрая EMA на prev bar — должна быть выше EMA Slow для входа (ema50 в sample)'),

            Number::make('EMA Slow', 'ema_slow')
                ->default(200)
                ->min(1)
                ->hint('Медленная EMA. Kline limit должен быть заметно больше этого значения (ema200 в sample)'),

            Number::make('Min ADX', 'adx_min')
                ->default(25)
                ->hint('Минимальный ADX для входа (ADX_THRESHOLD = 25 в sample)'),

            Number::make('Trend ADX Threshold', 'trend_adx_threshold')
                ->default(30)
                ->min(1)
                ->hint('Порог сильного тренда: выше — режим Hybrid, ниже — Sniper (TREND_ADX = 30 в sample)'),

            Number::make('RSI Limit Sniper', 'rsi_limit_sniper')
                ->default(55)
                ->step(0.01)
                ->hint('Макс. RSI для входа в режиме Sniper (боковик, ADX ≤ порога; лимит 55 в sample)'),

            Number::make('RSI Limit Hybrid', 'rsi_limit_hybrid')
                ->default(75)
                ->step(0.01)
                ->hint('Макс. RSI для входа в режиме Hybrid (тренд, ADX > порога; лимит 75 в sample)'),
        ];
    }

    protected function detailFields(): iterable
    {
        return [
            ID::make(),

            Select::make('Interval', 'interval'),

            Number::make('Period', 'period'),
            Number::make('Kline limit', 'kline_limit'),
            Number::make('EMA Fast', 'ema_fast'),
            Number::make('EMA Slow', 'ema_slow'),
            Number::make('Min ADX', 'adx_min'),
            Number::make('Trend ADX Threshold', 'trend_adx_threshold'),
            Number::make('RSI Limit Sniper', 'rsi_limit_sniper'),
            Number::make('RSI Limit Hybrid', 'rsi_limit_hybrid'),
        ];
    }
}