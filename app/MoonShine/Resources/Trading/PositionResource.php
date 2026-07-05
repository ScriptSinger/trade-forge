<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Trading;

use App\Enums\PositionStatus;
use App\Models\Bot;
use App\Models\Position;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Support\Attributes\Icon;
use MoonShine\Support\Enums\Action;
use MoonShine\Support\ListOf;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Preview;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

#[Icon('rectangle-stack')]
final class PositionResource extends TradingResource
{
    protected string $model = Position::class;

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
        return __('trading.resources.positions');
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
            Number::make('Вход', 'entry_price')->sortable(),
            Number::make('Кол-во', 'quantity')->sortable(),
            Enum::make('Статус', 'status')->attach(PositionStatus::class),
            Date::make('Открыта', 'opened_at')
                ->withTime()
                ->format('d.m.Y H:i'),
        ];
    }

    protected function detailFields(): iterable
    {
        return [
            Preview::make()->changePreview(fn() => 
                \MoonShine\UI\Components\Alert::make(
                    icon: 'briefcase',
                    type: 'info',
                )->content('<b>Позиции</b> — это ваш текущий торговый портфель. Запись создается в момент покупки актива и закрывается при его продаже. Здесь отображается "инвентарь" бота: что куплено, по какой цене и какие защитные уровни выставлены.')
            )->withoutWrapper(),

            ID::make(),
            BelongsTo::make(
                'Торговый бот',
                'bot',
                formatted: static fn (Bot $model): string => $model->name,
                resource: BotResource::class,
            ),
            Text::make('Торговая пара', 'symbol'),
            Number::make('Цена входа (Entry Price)', 'entry_price'),
            Number::make('Количество актива', 'quantity'),
            
            Number::make('Stop Loss (SL)', 'sl')
                ->hint('Уровень цены для автоматического закрытия в убыток (защита капитала)'),
            
            Number::make('Take Profit (TP)', 'tp')
                ->hint('Уровень цены для автоматической фиксации прибыли'),
            
            Switcher::make('Безубыток (Break Even)', 'be_activated')
                ->hint('Активирован ли перенос SL в цену входа'),
            
            Switcher::make('Трейлинг-стоп', 'trailing_active')
                ->hint('Активировано ли динамическое подтягивание стоп-лосса за ценой'),
            
            Switcher::make('Частичная фиксация', 'half_sold')
                ->hint('Была ли продана часть позиции для снижения риска'),

            Enum::make('Текущий статус', 'status')->attach(PositionStatus::class),
            Date::make('Дата и время открытия', 'opened_at')->withTime()->format('d.m.Y H:i:s'),
            Date::make('Дата и время закрытия', 'closed_at')->withTime()->format('d.m.Y H:i:s'),
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
            Enum::make('Статус', 'status')->attach(PositionStatus::class),
        ];
    }
}
