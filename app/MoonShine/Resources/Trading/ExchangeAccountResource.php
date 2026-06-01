<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Trading;

use App\Enums\ExchangeAccountStatus;
use App\Enums\ExchangeProvider;
use App\Models\ExchangeAccount;
use App\Models\User;
use App\MoonShine\Resources\Trading\Handlers\CheckConnectionHandler;
use App\MoonShine\Resources\Trading\Pages\ExchangeAccountFormPage;
use Illuminate\Validation\Rules\Enum as EnumRule;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Crud\Handlers\Handler;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Pages\Crud\DetailPage;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Support\Attributes\Icon;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Password;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

#[Icon('wallet')]
#[Group('Trading', 'wallet')]
#[Order(1)]
final class ExchangeAccountResource extends TradingResource
{
    protected string $model = ExchangeAccount::class;

    protected string $column = 'name';

    protected array $with = ['user'];

    protected function handlers(): ListOf
    {
        return new ListOf(Handler::class, [
            CheckConnectionHandler::make('Check connection')->alias('check-connection'),
        ]);
    }

    protected function pages(): array
    {
        return [
            IndexPage::class,
            ExchangeAccountFormPage::class,
            DetailPage::class,
        ];
    }

    public function getTitle(): string
    {
        return 'Exchange Accounts';
    }

    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make(
                'User',
                'user',
                formatted: static fn(User $model): string => sprintf('%s (%s)', $model->name, $model->email),
                resource: UserResource::class,
            ),
            Enum::make('Exchange', 'exchange')->attach(ExchangeProvider::class),
            Text::make('Name', 'name'),
            Switcher::make('Testnet', 'testnet'),
            Enum::make('Status', 'status')->attach(ExchangeAccountStatus::class),
            Date::make('Last checked at', 'last_checked_at')
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
                    formatted: static fn(User $model): string => sprintf('%s (%s)', $model->name, $model->email),
                    resource: UserResource::class,
                )
                    ->creatable()
                    ->required(),
                Enum::make('Exchange', 'exchange')
                    ->attach(ExchangeProvider::class)
                    ->required(),
                Text::make('Name', 'name')->nullable(),
                Text::make('API key', 'api_key')->eye(),
                Text::make('API secret', 'api_secret')->eye(),
                Switcher::make('Testnet', 'testnet'),
                Enum::make('Status', 'status')
                    ->attach(ExchangeAccountStatus::class)
                    ->required(),
                Date::make('Last checked at', 'last_checked_at')
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
                formatted: static fn(User $model): string => sprintf('%s (%s)', $model->name, $model->email),
                resource: UserResource::class,
            ),
            Enum::make('Exchange', 'exchange')->attach(ExchangeProvider::class),
            Enum::make('Status', 'status')->attach(ExchangeAccountStatus::class),
            Switcher::make('Testnet', 'testnet'),
        ];
    }

    public function formRules(DataWrapperContract $item): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'exchange' => ['required', new EnumRule(ExchangeProvider::class)],
            'name' => ['nullable', 'string', 'max:255'],
            'api_key' => [
                ...$item->getKey() !== null ? ['sometimes', 'nullable'] : ['required'],
                'string',
            ],
            'api_secret' => [
                ...$item->getKey() !== null ? ['sometimes', 'nullable'] : ['required'],
                'string',
            ],
            'testnet' => ['boolean'],
            'status' => ['required', new EnumRule(ExchangeAccountStatus::class)],
            'last_checked_at' => ['nullable', 'date'],
        ];
    }
}
