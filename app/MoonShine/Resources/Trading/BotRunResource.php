<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Trading;

use App\Enums\BotRunStatus;
use App\Enums\TradeSignal;
use App\Models\Bot;
use App\Models\BotRun;
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
use MoonShine\UI\Fields\Json;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;

#[Icon('arrow-path')]
#[Group('Trading', 'arrow-path')]
#[Order(4)]
final class BotRunResource extends TradingResource
{
    protected string $model = BotRun::class;

    protected string $column = 'symbol';

    protected array $with = ['bot'];

    public function getTitle(): string
    {
        return 'Bot Runs';
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
            Number::make('Market price', 'market_price')->sortable(),
            Enum::make('Signal', 'signal')->attach(TradeSignal::class),
            Enum::make('Status', 'status')->attach(BotRunStatus::class),
            Date::make('Created at', 'created_at')
                ->format('d.m.Y H:i')
                ->sortable(),
        ];
    }

    protected function detailFields(): iterable
    {
        return [
            ID::make(),
            BelongsTo::make(
                'Bot',
                'bot',
                formatted: static fn (Bot $model): string => $model->name,
                resource: BotResource::class,
            ),
            Text::make('Symbol', 'symbol'),
            Number::make('Market price', 'market_price'),
            Enum::make('Signal', 'signal')->attach(TradeSignal::class),
            Textarea::make('Reason', 'reason'),
            Json::make('Indicators', 'indicators')->keyValue('Key', 'Value'),
            Enum::make('Status', 'status')->attach(BotRunStatus::class),
            Date::make('Created at', 'created_at')->format('d.m.Y H:i'),
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
                Number::make('Market price', 'market_price')
                    ->min(0)
                    ->step(0.00000001)
                    ->required(),
                Enum::make('Signal', 'signal')
                    ->attach(TradeSignal::class)
                    ->required(),
                Json::make('Indicators', 'indicators')
                    ->keyValue('Key', 'Value')
                    ->nullable(),
                Textarea::make('Reason', 'reason')->nullable(),
                Enum::make('Status', 'status')
                    ->attach(BotRunStatus::class)
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
            Enum::make('Signal', 'signal')->attach(TradeSignal::class),
            Enum::make('Status', 'status')->attach(BotRunStatus::class),
        ];
    }

    public function formRules(DataWrapperContract $item): array
    {
        return [
            'bot_id' => ['required', 'integer', 'exists:bots,id'],
            'symbol' => ['required', 'string', 'max:255'],
            'market_price' => ['required', 'numeric', 'min:0'],
            'signal' => ['required', new EnumRule(TradeSignal::class)],
            'indicators' => ['nullable', 'array'],
            'reason' => ['nullable', 'string'],
            'status' => ['required', new EnumRule(BotRunStatus::class)],
        ];
    }
}

