<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Trading;

use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Bot;
use App\Models\ExchangeAccount;
use App\Models\Order;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order as MenuOrder;
use MoonShine\Support\Attributes\Icon;
use MoonShine\Support\Enums\Action;
use MoonShine\Support\ListOf;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Json;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Preview;
use MoonShine\UI\Fields\Text;

#[Icon('clipboard-document-list')]
#[Group('Trading', 'clipboard-document-list')]
#[MenuOrder(5)]
final class OrderResource extends TradingResource
{
    protected string $model = Order::class;

    protected string $column = 'symbol';

    protected array $with = ['bot', 'exchangeAccount'];

    protected function activeActions(): ListOf
    {
        return parent::activeActions()
            ->except(Action::CREATE, Action::UPDATE)
            ->prepend(Action::VIEW);
    }

    public function getTitle(): string
    {
        return 'Ордера';
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
            BelongsTo::make(
                'Аккаунт',
                'exchangeAccount',
                formatted: static fn (ExchangeAccount $model): string => sprintf(
                    '%s (%s)',
                    $model->name ?: 'ID: '.$model->id,
                    $model->exchange->value,
                ),
                resource: ExchangeAccountResource::class,
            ),
            Text::make('Пара', 'symbol')->sortable(),
            Enum::make('Сторона', 'side')
                ->attach(OrderSide::class)
                ->badge(fn($value) => match($value?->value ?? $value) {
                    OrderSide::Buy->value => 'green',
                    OrderSide::Sell->value => 'red',
                    default => 'gray',
                }),
            Enum::make('Статус', 'status')
                ->attach(OrderStatus::class)
                ->badge(fn($value) => match($value?->value ?? $value) {
                    OrderStatus::Filled->value => 'green',
                    OrderStatus::Failed->value, OrderStatus::Rejected->value => 'red',
                    OrderStatus::New->value, OrderStatus::Placed->value => 'blue',
                    OrderStatus::PartiallyFilled->value => 'yellow',
                    default => 'gray',
                }),
            Number::make('Цена', 'price')->sortable(),
            Number::make('Кол-во', 'quantity')->sortable(),
            Date::make('Дата', 'created_at')
                ->format('d.m.Y H:i')
                ->sortable(),
        ];
    }

    protected function detailFields(): iterable
    {
        return [
            Preview::make()->changePreview(fn() => 
                \MoonShine\UI\Components\Alert::make(
                    icon: 'receipt-percent',
                    type: 'info',
                )->content('<b>Ордера</b> — это финансовые транзакции на бирже. Данные загружаются напрямую из отчетов биржи и не подлежат ручному изменению для обеспечения точности баланса.')
            )->withoutWrapper(),

            ID::make(),
            BelongsTo::make(
                'Торговый бот',
                'bot',
                formatted: static fn (Bot $model): string => $model->name,
                resource: BotResource::class,
            ),
            BelongsTo::make(
                'Аккаунт биржи',
                'exchangeAccount',
                formatted: static fn (ExchangeAccount $model): string => sprintf(
                    '%s (%s)',
                    $model->name ?: 'ID: '.$model->id,
                    $model->exchange->value,
                ),
                resource: ExchangeAccountResource::class,
            ),
            Text::make('Торговая пара', 'symbol'),
            Enum::make('Направление (Buy/Sell)', 'side')
                ->attach(OrderSide::class)
                ->badge(fn($value) => match($value?->value ?? $value) {
                    OrderSide::Buy->value => 'green',
                    OrderSide::Sell->value => 'red',
                    default => 'gray',
                }),
            Enum::make('Тип ордера (Market/Limit)', 'type')->attach(OrderType::class),
            Number::make('Цена исполнения (USDT за 1 ед.)', 'price'),
            Number::make('Исполненное количество', 'quantity'),
            Enum::make('Статус исполнения', 'status')
                ->attach(OrderStatus::class)
                ->badge(fn($value) => match($value?->value ?? $value) {
                    OrderStatus::Filled->value => 'green',
                    OrderStatus::Failed->value, OrderStatus::Rejected->value => 'red',
                    OrderStatus::New->value, OrderStatus::Placed->value => 'blue',
                    OrderStatus::PartiallyFilled->value => 'yellow',
                    default => 'gray',
                }),
            Text::make('ID ордера на бирже (Exchange ID)', 'exchange_order_id'),
            Json::make('Сырой ответ от API биржи', 'raw_response'),
            Date::make('Дата и время сделки', 'created_at')->format('d.m.Y H:i:s'),
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
            Enum::make('Сторона', 'side')->attach(OrderSide::class),
            Enum::make('Статус', 'status')->attach(OrderStatus::class),
        ];
    }
}
