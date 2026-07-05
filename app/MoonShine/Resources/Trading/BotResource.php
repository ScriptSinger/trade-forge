<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Trading;

use App\Enums\BotStatus;
use App\Models\Bot;
use App\Models\ExchangeAccount;
use App\Models\Strategy;
use App\Models\User;
use Illuminate\Validation\Rules\Enum as EnumRule;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Support\Attributes\Icon;
use MoonShine\Support\Enums\Action;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Preview;
use MoonShine\UI\Fields\Text;

#[Icon('cpu-chip')]
final class BotResource extends TradingResource
{
    protected string $model = Bot::class;

    protected string $column = 'name';

    protected array $with = ['user', 'exchangeAccount', 'strategy'];

    protected function activeActions(): ListOf
    {
        return parent::activeActions()->prepend(Action::VIEW);
    }

    public function getTitle(): string
    {
        return __('trading.resources.bots');
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
                formatted: static fn(Strategy $model): string => $model->name,
                resource: StrategyResource::class,
            ),
            Text::make('Название', 'name')->sortable(),
            Enum::make('Статус', 'status')
                ->attach(BotStatus::class)
                ->badge(fn($value) => match($value?->value ?? $value) {
                    BotStatus::Active->value => 'green',
                    BotStatus::Paused->value => 'yellow',
                    BotStatus::Archived->value => 'red',
                    default => 'gray',
                }),
            Date::make('Последний запуск', 'last_run_at')
                ->withTime()
                ->format('d.m.Y H:i'),
        ];
    }

    protected function detailFields(): iterable
    {
        return [
            Preview::make()->changePreview(
                fn() =>
                \MoonShine\UI\Components\Alert::make(
                    icon: 'information-circle',
                    type: 'info',
                )->content('<b>Боты</b> связывают аккаунт биржи со стратегией. Риск, размер позиции и лимиты настраиваются в <b>Risk settings</b> выбранной стратегии — не на уровне бота.')
            )->withoutWrapper(),

            ID::make(),
            BelongsTo::make(
                'Владелец',
                'user',
                formatted: static fn(User $model): string => sprintf('%s (%s)', $model->name, $model->email),
                resource: UserResource::class,
            ),
            BelongsTo::make(
                'Аккаунт биржи',
                'exchangeAccount',
                resource: ExchangeAccountResource::class,
            ),
            BelongsTo::make(
                'Стратегия',
                'strategy',
                resource: StrategyResource::class,
            ),
            Text::make('Название', 'name'),
            Enum::make('Текущий статус', 'status')->attach(BotStatus::class),
            Date::make('Последний запуск', 'last_run_at')
                ->withTime()
                ->format('d.m.Y H:i'),
        ];
    }

    protected function formFields(): iterable
    {
        return [
            Preview::make()->changePreview(
                fn() =>
                \MoonShine\UI\Components\Alert::make(
                    icon: 'information-circle',
                    type: 'info',
                )->content('<b>Боты</b> связывают аккаунт биржи со стратегией. Риск, размер позиции и лимиты настраиваются в <b>Risk settings</b> выбранной стратегии — не на уровне бота.')
            )->withoutWrapper(),

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
                    formatted: static fn(Strategy $model): string => $model->name,
                    resource: StrategyResource::class,
                )
                    ->creatable()
                    ->hint('Стратегия с Entry/Risk settings — от неё зависят все торговые параметры')
                    ->required(),
                Text::make('Название', 'name')
                    ->hint('Произвольное имя для этого экземпляра бота')
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

    protected function indexButtons(): iterable
    {
        return [
            ActionButton::make(
                'Запустить',
                fn(Bot $item) => route('bot.run.manual', ['bot' => $item->id])
            )
                ->icon('play-circle')
                ->primary()
                ->canSee(fn(Bot $item) => $item->status === BotStatus::Active),
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
            Enum::make('Статус', 'status')
                ->attach(BotStatus::class)
                ->badge(fn($value) => match($value?->value ?? $value) {
                    BotStatus::Active->value => 'green',
                    BotStatus::Paused->value => 'yellow',
                    BotStatus::Archived->value => 'red',
                    default => 'gray',
                }),
        ];
    }

    public function formRules(DataWrapperContract $item): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'exchange_account_id' => ['required', 'integer', 'exists:exchange_accounts,id'],
            'strategy_id' => ['required', 'integer', 'exists:strategies,id'],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', new EnumRule(BotStatus::class)],
            'last_checked_at' => ['nullable', 'date'],
        ];
    }
}
