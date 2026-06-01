<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Trading;

use App\Enums\PositionStatus;
use App\Models\Bot;
use App\Models\Position;
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
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

#[Icon('rectangle-stack')]
#[Group('Trading', 'rectangle-stack')]
#[Order(6)]
final class PositionResource extends TradingResource
{
    protected string $model = Position::class;

    protected string $column = 'symbol';

    protected array $with = ['bot'];

    public function getTitle(): string
    {
        return 'Positions';
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
            Number::make('Quantity', 'quantity')->sortable(),
            Number::make('SL', 'sl')->sortable(),
            Number::make('TP', 'tp')->sortable(),
            Switcher::make('BE activated', 'be_activated'),
            Switcher::make('Trailing active', 'trailing_active'),
            Switcher::make('Half sold', 'half_sold'),
            Enum::make('Status', 'status')->attach(PositionStatus::class),
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
                Number::make('Quantity', 'quantity')
                    ->min(0)
                    ->step(0.00000001)
                    ->required(),
                Number::make('SL', 'sl')
                    ->min(0)
                    ->step(0.00000001)
                    ->nullable(),
                Number::make('TP', 'tp')
                    ->min(0)
                    ->step(0.00000001)
                    ->nullable(),
                Switcher::make('BE activated', 'be_activated'),
                Switcher::make('Trailing active', 'trailing_active'),
                Switcher::make('Half sold', 'half_sold'),
                Enum::make('Status', 'status')
                    ->attach(PositionStatus::class)
                    ->required(),
                Date::make('Opened at', 'opened_at')
                    ->withTime()
                    ->format('d.m.Y H:i')
                    ->default(now()->format('Y-m-d\TH:i'))
                    ->required(),
                Date::make('Closed at', 'closed_at')
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
                'Bot',
                'bot',
                formatted: static fn (Bot $model): string => $model->name,
                resource: BotResource::class,
            ),
            Text::make('Symbol', 'symbol'),
            Enum::make('Status', 'status')->attach(PositionStatus::class),
        ];
    }

    public function formRules(DataWrapperContract $item): array
    {
        return [
            'bot_id' => ['required', 'integer', 'exists:bots,id'],
            'symbol' => ['required', 'string', 'max:255'],
            'entry_price' => ['required', 'numeric', 'min:0'],
            'quantity' => ['required', 'numeric', 'min:0'],
            'sl' => ['nullable', 'numeric', 'min:0'],
            'tp' => ['nullable', 'numeric', 'min:0'],
            'be_activated' => ['boolean'],
            'trailing_active' => ['boolean'],
            'half_sold' => ['boolean'],
            'status' => ['required', new EnumRule(PositionStatus::class)],
            'opened_at' => ['required', 'date'],
            'closed_at' => ['nullable', 'date'],
        ];
    }
}

