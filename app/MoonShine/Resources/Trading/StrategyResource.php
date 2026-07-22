<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Trading;

use App\Models\Strategy;
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
        return __('trading.resources.strategies');
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
            Collapse::make('📖 Как работает алгоритм', [
                Box::make([
                    Heading::make('🔍 Сканер'),
                    FlexibleRender::make('Бот берёт TOP-30 пар Bybit spot по волатильности (кэш в Risk settings). Стейблкоины и прочие тикеры исключаются по паттернам в <b>Risk settings → Исключить из сканера</b>. За цикл анализируется до одного входа — после сделки цикл завершается.'),

                    Heading::make('✅ Условия входа')->class('mt-4'),
                    FlexibleRender::make('Все фильтры должны пройти:<br>
                        • <b>Breakout</b> — цена выше максимума за <b>Period</b> свечей.<br>
                        • <b>ADX</b> ≥ <b>Min ADX</b>, <b>EMA fast</b> &gt; <b>EMA slow</b>.<br>
                        • <b>RSI</b> ниже лимита (Sniper или Hybrid — по ADX на входе).<br>
                        • <b>Volume</b> — объём выше среднего.'),

                    Heading::make('🎯 Strategy mode (1–4)')->class('mt-4'),
                    FlexibleRender::make('<b>Entry settings → Strategy mode</b> (как STRATEGY_MODE в sample):<br>
                        • <b>1 Серфер</b> — trailing без TP, TG «Ракета!» при активации.<br>
                        • <b>2 Гибрид</b> — partial TP + trailing остатка (доля/BE в Risk settings).<br>
                        • <b>3 Умный Серфер</b> — ADX &gt; порог → Серфер, иначе Sniper.<br>
                        • <b>4 Умный Гибрид</b> — ADX &gt; порог → Hybrid, иначе Sniper (рекомендуется).<br>
                        <b>Sniper:</b> полный SL/TP. <b>Hybrid:</b> Hybrid TP portion + BE multiplier + Trailing Pct (Risk).'),

                    Heading::make('🛡️ BTC filter (дневная цель)')->class('mt-4'),
                    FlexibleRender::make('<b>BTC Trend Filter</b> не блокирует вход. При достижении дневной цели, если BTC не в аптренде (EMA fast &gt; slow), бот перестаёт открывать позиции и закрывает Sniper-позиции (SidewaysMarketGuard).'),

                    Heading::make('💰 Риск и исполнение')->class('mt-4'),
                    FlexibleRender::make('Размер позиции, SL/TP, лимит позиций, дневная цель — в <b>Risk settings</b>.<br>
                        <b>Entry settings:</b> Interval, Period, Kline limit (в sample = 1000).'),
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
                    Heading::make('BTC filter (daily target guard)'),

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
