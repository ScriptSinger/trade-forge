<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Trading;

use App\Enums\ExchangeAccountStatus;
use App\Enums\ExchangeProvider;
use App\Models\ExchangeAccount;
use App\Models\User;
use App\Services\Exchange\Bybit\BybitExchangeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Enum as EnumRule;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Pages\Crud\DetailPage;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Support\Attributes\Icon;
use MoonShine\Support\Enums\Action;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Preview;
use MoonShine\UI\Fields\Text;

#[Icon('wallet')]
final class ExchangeAccountResource extends TradingResource
{
    protected string $model = ExchangeAccount::class;

    protected string $column = 'name';

    protected array $with = ['user'];

    public function query(): Builder
    {
        return parent::query()->withCount(['bots', 'orders']);
    }

    protected function activeActions(): ListOf
    {
        return parent::activeActions()->prepend(Action::VIEW);
    }

    protected function pages(): array
    {
        return [
            IndexPage::class,
            FormPage::class,
            DetailPage::class,
        ];
    }

    public function getTitle(): string
    {
        return __('trading.resources.exchange_accounts');
    }

    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make(
                'Пользователь',
                'user',
                formatted: static fn (User $model): string => sprintf('%s (%s)', $model->name, $model->email),
                resource: UserResource::class,
            ),

            Enum::make('Биржа', 'exchange')->attach(ExchangeProvider::class),
            Text::make('Название', 'name'),
            Text::make('Ботов', 'bots_count', static function ($item) {
                return $item->bots_count ?? $item->bots()->count();
            })
                ->badge('blue')
                ->sortable(),
            Text::make('Ордеров', 'orders_count', static function ($item) {
                return $item->orders_count ?? $item->orders()->count();
            })
                ->badge('gray')
                ->sortable(),
            Text::make('API URL', 'api_url'),
            Enum::make('Статус', 'status')
                ->attach(ExchangeAccountStatus::class)
                ->badge(fn ($value) => match ($value?->value ?? $value) {
                    ExchangeAccountStatus::Active->value => 'green',
                    ExchangeAccountStatus::Disabled->value => 'yellow',
                    ExchangeAccountStatus::Error->value => 'red',
                    default => 'gray',
                }),
        ];
    }

    protected function detailFields(): iterable
    {
        return [
            ID::make(),
            BelongsTo::make(
                'Пользователь',
                'user',
                formatted: static fn (User $model): string => sprintf('%s (%s)', $model->name, $model->email),
                resource: UserResource::class,
            ),
            Enum::make('Биржа', 'exchange')->attach(ExchangeProvider::class),
            Text::make('Название', 'name'),
            Text::make('API URL', 'api_url'),
            Enum::make('Статус', 'status')
                ->attach(ExchangeAccountStatus::class)
                ->badge(fn ($value) => match ($value?->value ?? $value) {
                    ExchangeAccountStatus::Active->value => 'green',
                    ExchangeAccountStatus::Disabled->value => 'yellow',
                    ExchangeAccountStatus::Error->value => 'red',
                    default => 'gray',
                }),

            Preview::make('Текущий баланс (USDT)', 'balance')
                ->changePreview(function ($value, $field) {
                    $item = $field->getData()?->getOriginal();

                    if (! $item instanceof ExchangeAccount) {
                        return '<span class="text-gray-400">—</span>';
                    }

                    try {
                        $query = app(BybitExchangeService::class)->queryAccountBalance($item, 'USDT');

                        if (! $query->ok()) {
                            return '<span class="text-red-500">Ошибка Bybit ['.$query->retCode.']: '.$query->retMsg.'</span>';
                        }

                        $balance = $query->snapshot;

                        if ($balance === null || ! $balance->isPresent()) {
                            return '<span class="text-yellow-500">USDT не найден (баланс 0?)</span>';
                        }

                        $wallet = number_format($balance->wallet, 2);
                        $free = number_format($balance->free, 2);

                        if ($balance->locked > 0) {
                            $locked = number_format($balance->locked, 2);

                            return '<span class="text-green-500 font-bold">'.$wallet.' USDT</span>'
                                .'<br><span class="text-gray-500 text-sm">доступно: '.$free.' · заблокировано: '.$locked.'</span>';
                        }

                        return '<span class="text-green-500 font-bold">'.$wallet.' USDT</span>'
                            .'<br><span class="text-gray-500 text-sm">доступно: '.$free.'</span>';
                    } catch (\Exception $e) {
                        return '<span class="text-red-500 italic">Ошибка: '.$e->getMessage().'</span>';
                    }
                }),

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
                    formatted: static fn (User $model): string => sprintf('%s (%s)', $model->name, $model->email),
                    resource: UserResource::class,
                )
                    ->creatable()
                    ->required(),
                Enum::make('Биржа', 'exchange')
                    ->attach(ExchangeProvider::class)
                    ->required(),
                Text::make('Название', 'name')->nullable(),
                Text::make('API ключ', 'api_key')->eye(),
                Text::make('API секрет', 'api_secret')->eye(),
                Text::make('API URL', 'api_url')
                    ->default('https://api.bybit.com')
                    ->hint('Mainnet: https://api.bybit.com | Testnet: https://api-testnet.bybit.com')
                    ->required(),
                Enum::make('Статус', 'status')
                    ->attach(ExchangeAccountStatus::class)
                    ->required(),
            ]),
        ];
    }

    protected function filters(): iterable
    {
        return [
            BelongsTo::make(
                'Пользователь',
                'user',
                formatted: static fn (User $model): string => sprintf('%s (%s)', $model->name, $model->email),
                resource: UserResource::class,
            ),
            Enum::make('Биржа', 'exchange')->attach(ExchangeProvider::class),
            Enum::make('Статус', 'status')
                ->attach(ExchangeAccountStatus::class)
                ->badge(fn ($value) => match ($value?->value ?? $value) {
                    ExchangeAccountStatus::Active->value => 'green',
                    ExchangeAccountStatus::Disabled->value => 'yellow',
                    ExchangeAccountStatus::Error->value => 'red',
                    default => 'gray',
                }),
        ];
    }

    public function formRules(DataWrapperContract $item): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'exchange' => ['required', new EnumRule(ExchangeProvider::class)],
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'api_key' => [
                $item->exists ? 'sometimes' : 'required',
                'string',
                'min:10',
            ],
            'api_secret' => [
                $item->exists ? 'sometimes' : 'required',
                'string',
                'min:10',
            ],
            'api_url' => ['required', 'url', 'max:255'],
            'status' => ['required', new EnumRule(ExchangeAccountStatus::class)],
        ];
    }
}
