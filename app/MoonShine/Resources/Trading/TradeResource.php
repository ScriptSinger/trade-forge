<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Trading;

use App\Models\Bot;
use App\Models\Trade;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;
use MoonShine\Support\Enums\Action;
use MoonShine\Support\ListOf;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Preview;
use MoonShine\UI\Fields\Text;

#[Icon('banknotes')]
#[Group('Trading', 'banknotes')]
#[Order(7)]
final class TradeResource extends TradingResource
{
    protected string $model = Trade::class;

    protected string $column = 'symbol';

    protected array $with = ['bot'];

    protected function activeActions(): ListOf
    {
        return parent::activeActions()
            ->except(Action::CREATE, Action::UPDATE)
            ->prepend(Action::VIEW);
    }

    public function getTitle(): string
    {
        return 'Сделки';
    }

    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make(
                'Бот',
                'bot',
                formatted: static fn (Bot $model): string => $model->name,
                resource: BotResource::class,
            ),
            Text::make('Пара', 'symbol')->sortable(),
            Number::make('Профит', 'profit_loss')->sortable(),
            Number::make('%', 'profit_percent')->sortable(),
            Date::make('Дата закрытия', 'closed_at')
                ->withTime()
                ->format('d.m.Y H:i'),
        ];
    }

    protected function detailFields(): iterable
    {
        return [
            Preview::make()->changePreview(fn() => 
                \MoonShine\UI\Components\Alert::make(
                    icon: 'currency-dollar',
                    type: 'info',
                )->content('<b>Сделки</b> — это итоговый финансовый результат. Запись объединяет в себе покупку (вход) и продажу (выход). Здесь рассчитывается чистая прибыль или убыток за вычетом комиссий биржи.')
            )->withoutWrapper(),

            ID::make(),
            BelongsTo::make(
                'Торговый бот',
                'bot',
                formatted: static fn (Bot $model): string => $model->name,
                resource: BotResource::class,
            ),
            Text::make('Торговая пара', 'symbol'),
            
            Number::make('Цена входа', 'entry_price')
                ->hint('Цена, по которой актив был куплен'),
            
            Number::make('Цена выхода', 'exit_price')
                ->hint('Цена, по которой актив был полностью продан'),
            
            Number::make('Количество', 'quantity')
                ->hint('Объем торговой позиции'),
            
            Number::make('Прибыль/Убыток', 'profit_loss')
                ->hint('Чистый результат в валюте котировки (например, в USDT)'),
            
            Number::make('Прибыль %', 'profit_percent')
                ->hint('Процентное изменение капитала в этой сделке'),
            
            Number::make('Комиссии', 'fees')
                ->hint('Суммарные затраты на оплату услуг биржи за вход и выход'),

            Date::make('Время открытия', 'opened_at')->withTime()->format('d.m.Y H:i:s'),
            Date::make('Время закрытия', 'closed_at')->withTime()->format('d.m.Y H:i:s'),
        ];
    }

    protected function formFields(): iterable
    {
        return [];
    }

    protected function filters(): iterable
    {
        return [
            BelongsTo::make('Бот', 'bot', resource: BotResource::class),
            Text::make('Пара', 'symbol'),
        ];
    }
}
