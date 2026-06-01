<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Trading;

use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Bot;
use App\Models\ExchangeAccount;
use App\Models\Order;
use Illuminate\Validation\Rules\Enum as EnumRule;
use App\MoonShine\Resources\Trading\Pages\TradingFormPage;
use App\MoonShine\Resources\Trading\Pages\TradingIndexPage;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order as MenuOrder;
use MoonShine\Support\Attributes\Icon;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Json;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Text;

#[Icon('clipboard-document-list')]
#[Group('Trading', 'clipboard-document-list')]
#[MenuOrder(5)]
final class OrderResource extends TradingResource
{
    protected string $model = Order::class;

    protected string $column = 'symbol';

    protected array $with = ['bot', 'exchangeAccount'];

    public function getTitle(): string
    {
        return 'Orders';
    }

    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make(
                'Bot',
                'bot',
                formatted: static fn (Bot $model): string => $model->name,
                resource: BotResource::class,
            ),
            BelongsTo::make(
                'Exchange account',
                'exchangeAccount',
                formatted: static fn (ExchangeAccount $model): string => sprintf(
                    '%s (%s)',
                    $model->name ?: 'Account #'.$model->id,
                    $model->exchange->value,
                ),
                resource: ExchangeAccountResource::class,
            ),
            Text::make('Symbol', 'symbol')->sortable(),
            Enum::make('Side', 'side')->attach(OrderSide::class),
            Enum::make('Type', 'type')->attach(OrderType::class),
            Number::make('Price', 'price')->sortable(),
            Number::make('Quantity', 'quantity')->sortable(),
            Enum::make('Status', 'status')->attach(OrderStatus::class),
            Text::make('Exchange order id', 'exchange_order_id'),
            Date::make('Created at', 'created_at')
                ->format('d.m.Y H:i')
                ->sortable(),
        ];
    }

    protected function formFields(): iterable
    {
        return [
            Box::make([
                ID::make(),
                BelongsTo::make(
                    'Bot',
                    'bot',
                    formatted: static fn (Bot $model): string => $model->name,
                    resource: BotResource::class,
                )
                    ->creatable()
                    ->required(),
                BelongsTo::make(
                    'Exchange account',
                    'exchangeAccount',
                    formatted: static fn (ExchangeAccount $model): string => sprintf(
                        '%s (%s)',
                        $model->name ?: 'Account #'.$model->id,
                        $model->exchange->value,
                    ),
                    resource: ExchangeAccountResource::class,
                )
                    ->creatable()
                    ->required(),
                Text::make('Symbol', 'symbol')->required(),
                Enum::make('Side', 'side')
                    ->attach(OrderSide::class)
                    ->required(),
                Enum::make('Type', 'type')
                    ->attach(OrderType::class)
                    ->required(),
                Number::make('Price', 'price')
                    ->min(0)
                    ->step(0.00000001)
                    ->nullable(),
                Number::make('Quantity', 'quantity')
                    ->min(0)
                    ->step(0.00000001)
                    ->required(),
                Enum::make('Status', 'status')
                    ->attach(OrderStatus::class)
                    ->required(),
                Text::make('Exchange order id', 'exchange_order_id')->nullable(),
                Json::make('Raw response', 'raw_response')
                    ->keyValue('Key', 'Value')
                    ->nullable(),
            ]),
        ];
    }

    protected function filters(): iterable
    {
        return [
            BelongsTo::make(
                'Bot',
                'bot',
                formatted: static fn (Bot $model): string => $model->name,
                resource: BotResource::class,
            ),
            BelongsTo::make(
                'Exchange account',
                'exchangeAccount',
                formatted: static fn (ExchangeAccount $model): string => sprintf(
                    '%s (%s)',
                    $model->name ?: 'Account #'.$model->id,
                    $model->exchange->value,
                ),
                resource: ExchangeAccountResource::class,
            ),
            Text::make('Symbol', 'symbol'),
            Enum::make('Side', 'side')->attach(OrderSide::class),
            Enum::make('Type', 'type')->attach(OrderType::class),
            Enum::make('Status', 'status')->attach(OrderStatus::class),
        ];
    }

    public function formRules(DataWrapperContract $item): array
    {
        return [
            'bot_id' => ['required', 'integer', 'exists:bots,id'],
            'exchange_account_id' => ['required', 'integer', 'exists:exchange_accounts,id'],
            'symbol' => ['required', 'string', 'max:255'],
            'side' => ['required', new EnumRule(OrderSide::class)],
            'type' => ['required', new EnumRule(OrderType::class)],
            'price' => ['nullable', 'numeric', 'min:0'],
            'quantity' => ['required', 'numeric', 'min:0'],
            'status' => ['required', new EnumRule(OrderStatus::class)],
            'exchange_order_id' => ['nullable', 'string', 'max:255'],
            'raw_response' => ['nullable', 'array'],
        ];
    }
}
