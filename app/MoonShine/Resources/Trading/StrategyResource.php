<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Trading;

use App\Models\Strategy;
use App\MoonShine\Resources\Trading\StrategyEntrySettingsResource;
use App\MoonShine\Resources\Trading\StrategyRiskSettingsResource;
use App\MoonShine\Resources\Trading\StrategyBtcTrendFilterResource;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Laravel\Fields\Relationships\HasOne;
use MoonShine\Support\Attributes\Icon;
use MoonShine\UI\Components\Collapse;
use MoonShine\UI\Components\FlexibleRender;
use MoonShine\UI\Components\Heading;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

#[Icon('adjustments-horizontal')]
final class StrategyResource extends TradingResource
{
    protected string $model = Strategy::class;

    protected string $column = 'name';

    protected array $with = ['bots'];

    public function getTitle(): string
    {
        return 'Strategies';
    }

    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            Text::make('Название', 'name')->sortable(),
            Switcher::make('Активна', 'is_active'),
            Date::make('Создана', 'created_at')
                ->format('d.m.Y H:i')
                ->sortable(),
        ];
    }

    protected function formFields(): iterable
    {
        return [
            Collapse::make('📖 Как работает алгоритм (mode 4)', [
                Box::make([
                    Heading::make('🔍 Сканер'),
                    FlexibleRender::make('Бот берёт TOP-30 пар Bybit spot по объёму (кэш задаётся в Risk settings). За цикл анализируется один символ — после входа цикл завершается.'),

                    Heading::make('✅ Условия входа')->class('mt-4'),
                    FlexibleRender::make('Все фильтры должны пройти:<br>
                        • <b>BTC trend</b> — цена BTC выше EMA (если фильтр включён).<br>
                        • <b>Breakout</b> — цена выше максимума за <b>Period</b> свечей.<br>
                        • <b>ADX</b> ≥ <b>Min ADX</b>, <b>EMA fast</b> &gt; <b>EMA slow</b>.<br>
                        • <b>RSI</b> ниже лимита (Sniper или Hybrid — см. ниже).<br>
                        • <b>Volume</b> — объём выше среднего.'),

                    Heading::make('🎯 Режимы Sniper / Hybrid')->class('mt-4'),
                    FlexibleRender::make('Режим выбирается <b>в рантайме</b> по ADX открытой позиции:<br>
                        • <b>Sniper</b> (ADX ≥ Trend ADX Threshold) — трейлинг-стоп, без частичной фиксации.<br>
                        • <b>Hybrid</b> (ADX ниже порога) — при достижении TP продаётся 50% на бирже, остаток с трейлингом.'),

                    Heading::make('💰 Риск и исполнение')->class('mt-4'),
                    FlexibleRender::make('Размер позиции, SL/TP, лимит позиций, дневная цель, комиссия и мин. ордер — всё в <b>Risk settings</b>. Параметры Entry settings задают таймфрейм и пороги индикаторов.'),
                ]),
            ])->open(false),

            Box::make([
                ID::make(),
                Text::make('Название', 'name')
                    ->hint('Уникальное имя для идентификации стратегии')
                    ->required(),

                Box::make([
                    Heading::make('Entry settings'),

                    HasOne::make(
                        'Entry Settings',
                        'entrySettings',
                        resource: StrategyEntrySettingsResource::class
                    )->disableOutside(),
                ]),

                Box::make([
                    Heading::make('Risk settings'),

                    HasOne::make(
                        'Risk Settings',
                        'riskSettings',
                        resource: StrategyRiskSettingsResource::class
                    )->disableOutside(),
                ]),

                Box::make([
                    Heading::make('Market filter (BTC trend)'),

                    HasOne::make(
                        'BTC Trend Filter',
                        'btcTrendFilter',
                        resource: StrategyBtcTrendFilterResource::class
                    )->disableOutside(),
                ]),

                Switcher::make('Активна', 'is_active')
                    ->hint('Разрешить использование этой стратегии ботами'),
            ]),
        ];
    }

    protected function filters(): iterable
    {
        return [
            Text::make('Название', 'name'),
            Switcher::make('Активна', 'is_active'),
        ];
    }

    public function formRules(DataWrapperContract $item): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }
}