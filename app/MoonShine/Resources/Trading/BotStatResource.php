<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Trading;

use App\Models\Bot;
use App\Models\BotStat;
use Illuminate\Validation\Rules\Enum as EnumRule;
use App\MoonShine\Resources\Trading\Pages\TradingFormPage;
use App\MoonShine\Resources\Trading\Pages\TradingIndexPage;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Support\Attributes\Icon;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Text;

#[Icon('presentation-chart-line')]
final class BotStatResource extends TradingResource
{
    protected string $model = BotStat::class;

    protected string $column = 'date';

    protected array $with = ['bot'];

    public function getTitle(): string
    {
        return __('trading.resources.bot_stats');
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
            Date::make('Date', 'date')
                ->format('d.m.Y')
                ->sortable(),
            Number::make('Total trades', 'total_trades')->sortable(),
            Number::make('Wins', 'wins')->sortable(),
            Number::make('Losses', 'losses')->sortable(),
            Number::make('Winrate', 'winrate')->sortable(),
            Number::make('Profit', 'profit')->sortable(),
            Number::make('Fees', 'fees')->sortable(),
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
                Date::make('Date', 'date')
                    ->format('d.m.Y')
                    ->default(now()->toDateString())
                    ->required(),
                Number::make('Total trades', 'total_trades')
                    ->min(0)
                    ->step(1)
                    ->default(0)
                    ->required(),
                Number::make('Wins', 'wins')
                    ->min(0)
                    ->step(1)
                    ->default(0)
                    ->required(),
                Number::make('Losses', 'losses')
                    ->min(0)
                    ->step(1)
                    ->default(0)
                    ->required(),
                Number::make('Winrate', 'winrate')
                    ->step(0.01)
                    ->default(0)
                    ->required(),
                Number::make('Profit', 'profit')
                    ->step(0.00000001)
                    ->default(0)
                    ->required(),
                Number::make('Fees', 'fees')
                    ->step(0.00000001)
                    ->default(0)
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
            Date::make('Date', 'date')->format('d.m.Y'),
        ];
    }

    public function formRules(DataWrapperContract $item): array
    {
        return [
            'bot_id' => ['required', 'integer', 'exists:bots,id'],
            'date' => ['required', 'date'],
            'total_trades' => ['required', 'integer', 'min:0'],
            'wins' => ['required', 'integer', 'min:0'],
            'losses' => ['required', 'integer', 'min:0'],
            'winrate' => ['required', 'numeric', 'min:0'],
            'profit' => ['required', 'numeric'],
            'fees' => ['required', 'numeric', 'min:0'],
        ];
    }
}

