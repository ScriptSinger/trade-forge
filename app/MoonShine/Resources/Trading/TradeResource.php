<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Trading;

use App\Models\Bot;
use App\Models\Trade;
use App\MoonShine\Resources\Trading\Pages\TradingFormPage;
use App\MoonShine\Resources\Trading\Pages\TradingIndexPage;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Text;

#[Icon('banknotes')]
#[Group('Trading', 'banknotes')]
#[Order(7)]
final class TradeResource extends TradingResource
{
    protected string $model = Trade::class;

    protected string $column = 'symbol';

    protected array $with = ['bot'];

    public function getTitle(): string
    {
        return 'Trades';
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
            Text::make('Symbol', 'symbol')->sortable(),
            Number::make('Entry price', 'entry_price')->sortable(),
            Number::make('Exit price', 'exit_price')->sortable(),
            Number::make('Quantity', 'quantity')->sortable(),
            Number::make('Profit / loss', 'profit_loss')->sortable(),
            Number::make('Profit %', 'profit_percent')->sortable(),
            Number::make('Fees', 'fees')->sortable(),
            Date::make('Opened at', 'opened_at')
                ->withTime()
                ->format('d.m.Y H:i'),
            Date::make('Closed at', 'closed_at')
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
                    'Bot',
                    'bot',
                    formatted: static fn (Bot $model): string => $model->name,
                    resource: BotResource::class,
                )
                    ->creatable()
                    ->required(),
                Text::make('Symbol', 'symbol')->required(),
                Number::make('Entry price', 'entry_price')
                    ->min(0)
                    ->step(0.00000001)
                    ->required(),
                Number::make('Exit price', 'exit_price')
                    ->min(0)
                    ->step(0.00000001)
                    ->required(),
                Number::make('Quantity', 'quantity')
                    ->min(0)
                    ->step(0.00000001)
                    ->required(),
                Number::make('Profit / loss', 'profit_loss')
                    ->step(0.00000001)
                    ->required(),
                Number::make('Profit %', 'profit_percent')
                    ->step(0.01)
                    ->required(),
                Number::make('Fees', 'fees')
                    ->min(0)
                    ->step(0.00000001)
                    ->default(0)
                    ->required(),
                Date::make('Opened at', 'opened_at')
                    ->withTime()
                    ->format('d.m.Y H:i')
                    ->default(now()->format('Y-m-d\TH:i'))
                    ->required(),
                Date::make('Closed at', 'closed_at')
                    ->withTime()
                    ->format('d.m.Y H:i')
                    ->default(now()->format('Y-m-d\TH:i'))
                    ->required(),
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
            Text::make('Symbol', 'symbol'),
        ];
    }

    public function formRules(DataWrapperContract $item): array
    {
        return [
            'bot_id' => ['required', 'integer', 'exists:bots,id'],
            'symbol' => ['required', 'string', 'max:255'],
            'entry_price' => ['required', 'numeric', 'min:0'],
            'exit_price' => ['required', 'numeric', 'min:0'],
            'quantity' => ['required', 'numeric', 'min:0'],
            'profit_loss' => ['required', 'numeric'],
            'profit_percent' => ['required', 'numeric'],
            'fees' => ['required', 'numeric', 'min:0'],
            'opened_at' => ['required', 'date'],
            'closed_at' => ['required', 'date'],
        ];
    }
}
