<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Trading;

use App\Enums\BotStatus;
use App\Models\Bot;
use App\Models\ExchangeAccount;
use App\Models\Strategy;
use App\Models\User;
use Illuminate\Validation\Rules\Enum as EnumRule;
use App\MoonShine\Resources\Trading\Pages\TradingFormPage;
use App\MoonShine\Resources\Trading\Pages\TradingIndexPage;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Text;

#[Icon('cpu-chip')]
#[Group('Trading', 'cpu-chip')]
#[Order(3)]
final class BotResource extends TradingResource
{
    protected string $model = Bot::class;

    protected string $column = 'name';

    protected array $with = ['user', 'exchangeAccount', 'strategy'];

    public function getTitle(): string
    {
        return 'Bots';
    }

    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make(
                'User',
                'user',
                formatted: static fn (User $model): string => sprintf('%s <%s>', $model->name, $model->email),
                resource: UserResource::class,
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
            BelongsTo::make(
                'Strategy',
                'strategy',
                formatted: static fn (Strategy $model): string => sprintf('%s (%s)', $model->name, $model->type->value),
                resource: StrategyResource::class,
            ),
            Text::make('Name', 'name')->sortable(),
            Number::make('Risk per trade', 'risk_per_trade')->sortable(),
            Number::make('Max open positions', 'max_open_positions')->sortable(),
            Enum::make('Status', 'status')->attach(BotStatus::class),
            Date::make('Last run at', 'last_run_at')
                ->withTime()
                ->format('d.m.Y H:i'),
        ];
    }

    protected function formFields(): iterable
    {
        return [
            Box::make([
                ID::make(),
                BelongsTo::make(
                    'User',
                    'user',
                    formatted: static fn (User $model): string => sprintf('%s <%s>', $model->name, $model->email),
                    resource: UserResource::class,
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
                BelongsTo::make(
                    'Strategy',
                    'strategy',
                    formatted: static fn (Strategy $model): string => sprintf('%s (%s)', $model->name, $model->type->value),
                    resource: StrategyResource::class,
                )
                    ->creatable()
                    ->required(),
                Text::make('Name', 'name')->required(),
                Number::make('Risk per trade', 'risk_per_trade')
                    ->min(0.01)
                    ->step(0.01)
                    ->default(1.00)
                    ->required(),
                Number::make('Max open positions', 'max_open_positions')
                    ->min(1)
                    ->step(1)
                    ->default(1)
                    ->required(),
                Enum::make('Status', 'status')
                    ->attach(BotStatus::class)
                    ->required(),
                Date::make('Last run at', 'last_run_at')
                    ->withTime()
                    ->format('d.m.Y H:i')
                    ->nullable(),
            ]),
        ];
    }

    protected function filters(): iterable
    {
        return [
            BelongsTo::make(
                'User',
                'user',
                formatted: static fn (User $model): string => sprintf('%s <%s>', $model->name, $model->email),
                resource: UserResource::class,
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
            BelongsTo::make(
                'Strategy',
                'strategy',
                formatted: static fn (Strategy $model): string => sprintf('%s (%s)', $model->name, $model->type->value),
                resource: StrategyResource::class,
            ),
            Enum::make('Status', 'status')->attach(BotStatus::class),
        ];
    }

    public function formRules(DataWrapperContract $item): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'exchange_account_id' => ['required', 'integer', 'exists:exchange_accounts,id'],
            'strategy_id' => ['required', 'integer', 'exists:strategies,id'],
            'name' => ['required', 'string', 'max:255'],
            'risk_per_trade' => ['required', 'numeric', 'min:0.01'],
            'max_open_positions' => ['required', 'integer', 'min:1'],
            'status' => ['required', new EnumRule(BotStatus::class)],
            'last_run_at' => ['nullable', 'date'],
        ];
    }
}

