<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Trading;

use App\Enums\ExchangeAccountStatus;
use App\Enums\ExchangeProvider;
use App\Models\ExchangeAccount;
use App\Models\User;
use App\Services\Exchange\BybitExchangeService;
use App\MoonShine\Resources\Trading\Handlers\CheckConnectionHandler;
use App\MoonShine\Resources\Trading\Pages\ExchangeAccountFormPage;
use Illuminate\Validation\Rules\Enum as EnumRule;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Crud\Handlers\Handler;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Pages\Crud\DetailPage;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Support\Attributes\Icon;
use MoonShine\Support\Enums\Action;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Preview;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

#[Icon('wallet')]
final class ExchangeAccountResource extends TradingResource
{
    protected string $model = ExchangeAccount::class;

    protected string $column = 'name';

    protected array $with = ['user'];

    public function query(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::query()->withCount(['bots', 'orders']);
    }

    protected function activeActions(): ListOf
    {
        return parent::activeActions()->prepend(Action::VIEW);
    }

    protected function handlers(): ListOf
    {
        return new ListOf(Handler::class, [
            CheckConnectionHandler::make('Проверить соединение')->alias('check-connection'),
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
        return 'Аккаунты бирж';
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

            Enum::make('Биржа', 'exchange')->attach(ExchangeProvider::class),
            Text::make('Название', 'name'),
            Text::make('Ботов', 'bots_count', static function($item) {
                return $item->bots_count ?? $item->bots()->count();
            })
                ->badge('blue')
                ->sortable(),
            Text::make('Ордеров', 'orders_count', static function($item) {
                return $item->orders_count ?? $item->orders()->count();
            })
                ->badge('gray')
                ->sortable(),
            Text::make('API URL', 'api_url'),
            Enum::make('Статус', 'status')
                ->attach(ExchangeAccountStatus::class)
                ->badge(fn($value) => match($value?->value ?? $value) {
                    ExchangeAccountStatus::Active->value => 'green',
                    ExchangeAccountStatus::Disabled->value => 'yellow',
                    ExchangeAccountStatus::Error->value => 'red',
                    default => 'gray',
                }),
            Date::make('Проверен', 'last_checked_at')
                ->withTime()
                ->format('d.m.Y H:i'),
        ];
    }

    protected function detailFields(): iterable
    {
        return [
            ID::make(),
            BelongsTo::make(
                'Пользователь',
                'user',
                formatted: static fn(User $model): string => sprintf('%s (%s)', $model->name, $model->email),
                resource: UserResource::class,
            ),
            Enum::make('Биржа', 'exchange')->attach(ExchangeProvider::class),
            Text::make('Название', 'name'),
            Text::make('API URL', 'api_url'),
            Enum::make('Статус', 'status')
                ->attach(ExchangeAccountStatus::class)
                ->badge(fn($value) => match($value?->value ?? $value) {
                    ExchangeAccountStatus::Active->value => 'green',
                    ExchangeAccountStatus::Disabled->value => 'yellow',
                    ExchangeAccountStatus::Error->value => 'red',
                    default => 'gray',
                }),
            
            Preview::make('Текущий баланс (USDT)', 'balance')
                ->changePreview(function($value, $field) {
                    $item = $field->getData()?->getOriginal();

                    if (! $item instanceof ExchangeAccount) {
                        return '<span class="text-gray-400">—</span>';
                    }

                    try {
                        $service = app(BybitExchangeService::class);
                        $data = $service->getWalletBalance($item);
                        
                        $retCode = $data['retCode'] ?? -1;
                        $retMsg = $data['retMsg'] ?? 'Unknown error';

                        if ($retCode === 0) {
                            $list = $data['result']['list'] ?? [];
                            $totalBalance = 0;
                            $found = false;

                            foreach ($list as $acc) {
                                foreach ($acc['coin'] ?? [] as $coinData) {
                                    if ($coinData['coin'] === 'USDT') {
                                        $totalBalance = (float) ($coinData['walletBalance'] ?? 0);
                                        $found = true;
                                        break 2;
                                    }
                                }
                            }

                            if ($found) {
                                return '<span class="text-green-500 font-bold">' . number_format($totalBalance, 2) . ' USDT</span>';
                            }
                            
                            return '<span class="text-yellow-500">USDT не найден (баланс 0?)</span>';
                        }
                        
                        return '<span class="text-red-500">Ошибка Bybit [' . $retCode . ']: ' . $retMsg . '</span>';
                    } catch (\Exception $e) {
                        return '<span class="text-red-500 italic">Ошибка: ' . $e->getMessage() . '</span>';
                    }
                }),

            Date::make('Последняя проверка', 'last_checked_at')
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
                Date::make('Последняя проверка', 'last_checked_at')
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
            Enum::make('Биржа', 'exchange')->attach(ExchangeProvider::class),
            Enum::make('Статус', 'status')
                ->attach(ExchangeAccountStatus::class)
                ->badge(fn($value) => match($value?->value ?? $value) {
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
            'last_checked_at' => ['nullable', 'date'],
        ];
    }
}
