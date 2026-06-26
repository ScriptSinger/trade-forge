<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Trading;

use App\Enums\StrategyType;
use App\Models\Strategy;
use Illuminate\Validation\Rules\Enum as EnumRule;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;
use MoonShine\UI\Components\Collapse;
use MoonShine\UI\Components\FlexibleRender;
use MoonShine\UI\Components\Heading;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Json;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

#[Icon('adjustments-horizontal')]
#[Group('Trading', 'adjustments-horizontal')]
#[Order(2)]
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
            Enum::make('Тип', 'type')->attach(StrategyType::class),
            Switcher::make('Активна', 'is_active'),
            Date::make('Создана', 'created_at')
                ->format('d.m.Y H:i')
                ->sortable(),
        ];
    }

    protected function formFields(): iterable
    {
        return [
            Collapse::make('📖 Справочник алгоритмов', [
                Box::make([
                    Heading::make('📈 Trend (Трендовая)'),
                    FlexibleRender::make('Самая простая логика: сравнивает текущую цену со средней (SMA) за указанный <b>Период</b>. <br>
                        • <b>BUY:</b> Цена выше средней.<br>
                        • <b>SELL:</b> Цена ниже средней.'),

                    Heading::make('📉 Breakout (Пробойная)')->class('mt-4'),
                    FlexibleRender::make('Ищет выход цены за пределы диапазона (High/Low) последних свечей.<br>
                        • <b>BUY:</b> Цена пробила максимум за <b>Период</b>.<br>
                        • <b>SELL:</b> Цена пробила минимум за <b>Период</b>.'),

                    Heading::make('⚖️ Hybrid (Гибридная)')->class('mt-4'),
                    FlexibleRender::make('Комбинирует оба метода. Подает сигнал только в том случае, если <b>и Trend, и Breakout</b> показывают одинаковое направление.'),
                ])
            ])->open(false),

            Box::make([
                ID::make(),
                Text::make('Название', 'name')
                    ->hint('Уникальное имя для идентификации стратегии')
                    ->required(),
                Enum::make('Тип алгоритма', 'type')
                    ->attach(StrategyType::class)
                    ->hint('Выберите базовую логику (Trend, Breakout и т.д.)')
                    ->required(),

                Json::make('⚙️ Настройка фильтров', 'settings')
                    ->object()
                    ->fields([
                        Select::make('Таймфрейм', 'interval')
                            ->options([
                                '1' => '1 минута',
                                '3' => '3 минуты',
                                '5' => '5 минут',
                                '15' => '15 минут',
                                '30' => '30 минут',
                                '60' => '1 час',
                                '240' => '4 часа',
                                'D' => '1 день',
                            ])
                            ->hint('Интервал свечей, которые бот запрашивает с биржи')
                            ->default('1')
                            ->required(),

                        Number::make('Период', 'period')
                            ->hint('Количество свечей для расчета (SMA, Max/Min)')
                            ->default(20)
                            ->min(1)
                            ->required(),

                        Number::make('Минимум ADX', 'min_adx')
                            ->default(20),

                        Number::make('EMA Fast', 'ema_fast')
                            ->default(50)
                            ->hint('Быстрая EMA, по умолчанию 50'),

                        Number::make('EMA Slow', 'ema_slow')
                            ->default(200)
                            ->hint('Медленная EMA, по умолчанию 200'),

                        Number::make('ATR SL Множитель', 'sl_multiplier')
                            ->default(2.0)
                            ->step(0.1),

                        Number::make('ATR TP Множитель', 'tp_multiplier')
                            ->default(3.0)
                            ->step(0.1),

                        Number::make('Трейлинг отступ (%)', 'trailing_pct')
                            ->default(1.5)
                            ->step(0.1)
                            ->hint('На сколько % цена может упасть от пика до закрытия'),

                        Switcher::make('Фильтр EMA Fast / EMA Slow', 'check_market_regime')
                            ->default(true)
                            ->hint('Если, например, EMA 50 ниже EMA 200, бот не открывает сделки.'),
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
            Enum::make('Тип', 'type')->attach(StrategyType::class),
            Switcher::make('Активна', 'is_active'),
        ];
    }

    public function formRules(DataWrapperContract $item): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', new EnumRule(StrategyType::class)],
            'settings' => ['required', 'array'],
            'settings.interval' => ['required', 'string', 'max:10'],
            'settings.period' => ['required', 'integer', 'min:1'],
            'settings.min_adx' => ['nullable', 'numeric', 'min:0'],
            'settings.ema_fast' => ['nullable', 'numeric', 'min:0'],
            'settings.ema_slow' => ['nullable', 'numeric', 'min:0'],
            'settings.sl_multiplier' => ['nullable', 'numeric', 'min:0'],
            'settings.tp_multiplier' => ['nullable', 'numeric', 'min:0'],
            'settings.trailing_pct' => ['nullable', 'numeric', 'min:0'],
            'settings.check_market_regime' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }
}
