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
use MoonShine\Laravel\Fields\Relationships\HasMany;
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
        return 'Боты';
    }

    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make(
                'Пользователь',
                'user',
                formatted: static fn(User $model): string => sprintf('%s (%s)', $model->name, $model->email),
                resource: UserResource::class,
            ),
            BelongsTo::make(
                'Аккаунт биржи',
                'exchangeAccount',
                formatted: static fn(ExchangeAccount $model): string => sprintf(
                    '%s (%s)',
                    $model->name ?: 'Аккаунт #' . $model->id,
                    $model->exchange->value,
                ),
                resource: ExchangeAccountResource::class,
            ),
            BelongsTo::make(
                'Стратегия',
                'strategy',
                formatted: static fn(Strategy $model): string => sprintf('%s (%s)', $model->name, $model->type->value),
                resource: StrategyResource::class,
            ),
            Text::make('Название', 'name')->sortable(),
            Number::make('Риск на сделку', 'risk_per_trade')->sortable(),
            Number::make('Макс. позиций', 'max_open_positions')->sortable(),
            Enum::make('Статус', 'status')->attach(BotStatus::class),
            Date::make('Последний запуск', 'last_run_at')
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
                    'Пользователь',
                    'user',
                    formatted: static fn(User $model): string => sprintf('%s (%s)', $model->name, $model->email),
                    resource: UserResource::class,
                )
                    ->creatable()
                    ->hint('Владелец данного торгового бота')
                    ->required(),
                BelongsTo::make(
                    'Аккаунт биржи',
                    'exchangeAccount',
                    formatted: static fn(ExchangeAccount $model): string => sprintf(
                        '%s (%s)',
                        $model->name ?: 'Аккаунт #' . $model->id,
                        $model->exchange->value,
                    ),
                    resource: ExchangeAccountResource::class,
                )
                    ->creatable()
                    ->hint('API-ключи биржи, которые будет использовать бот')
                    ->required(),
                BelongsTo::make(
                    'Стратегия',
                    'strategy',
                    formatted: static fn(Strategy $model): string => sprintf('%s (%s)', $model->name, $model->type->value),
                    resource: StrategyResource::class,
                )
                    ->creatable()
                    ->hint('Алгоритм, по которому бот будет принимать решения')
                    ->required(),
                Text::make('Название', 'name')
                    ->hint('Произвольное имя для этого экземпляра бота')
                    ->required(),
                Text::make('Торговая пара', 'symbol')
                    ->hint('Например, BTCUSDT или ETHUSDT')
                    ->required(),
                Number::make('Риск на сделку', 'risk_per_trade')
                    ->hint('Размер одного ордера (в валюте котировки или %)')
                    ->min(0.01)
                    ->step(0.01)
                    ->default(1.00)
                    ->required(),
                Number::make('Макс. позиций', 'max_open_positions')
                    ->hint('Лимит одновременно открытых сделок для этого бота')
                    ->min(1)
                    ->step(1)
                    ->default(1)
                    ->required(),
                Enum::make('Статус', 'status')
                    ->attach(BotStatus::class)
                    ->hint('Текущее состояние бота (Активен/Пауза)')
                    ->required(),
                Date::make('Последний запуск', 'last_run_at')
                    ->hint('Время последней итерации торгового алгоритма')
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
                'Пользователь',
                'user',
                formatted: static fn(User $model): string => sprintf('%s (%s)', $model->name, $model->email),
                resource: UserResource::class,
            ),
            BelongsTo::make(
                'Аккаунт биржи',
                'exchangeAccount',
                formatted: static fn(ExchangeAccount $model): string => sprintf(
                    '%s (%s)',
                    $model->name ?: 'Аккаунт #' . $model->id,
                    $model->exchange->value,
                ),
                resource: ExchangeAccountResource::class,
            ),
            BelongsTo::make(
                'Стратегия',
                'strategy',
                formatted: static fn(Strategy $model): string => sprintf('%s (%s)', $model->name, $model->type->value),
                resource: StrategyResource::class,
            ),
            Enum::make('Статус', 'status')->attach(BotStatus::class),
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
