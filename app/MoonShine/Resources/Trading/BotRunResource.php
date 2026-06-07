<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Trading;

use App\Enums\BotRunStatus;
use App\Enums\TradeSignal;
use App\Models\Bot;
use App\Models\BotRun;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;
use MoonShine\Support\Enums\Action;
use MoonShine\Support\ListOf;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Json;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Preview;
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

    protected function activeActions(): ListOf
    {
        return parent::activeActions()
            ->except(Action::CREATE, Action::UPDATE)
            ->prepend(Action::VIEW);
    }

    public function getTitle(): string
    {
        return 'Логи ботов';
    }

    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make(
                'Бот',
                'bot',
                formatted: static fn (Bot $model): string => $model->name,
                resource: BotResource::class,
            ),
            Text::make('Пара', 'symbol')->sortable(),
            Number::make('Цена', 'market_price')->sortable(),
            Enum::make('Сигнал', 'signal')
                ->attach(TradeSignal::class)
                ->badge(fn($value) => match($value?->value ?? $value) {
                    TradeSignal::Buy->value => 'green',
                    TradeSignal::Sell->value => 'red',
                    default => 'gray',
                }),
            Enum::make('Статус', 'status')
                ->attach(BotRunStatus::class)
                ->badge(fn($value) => match($value?->value ?? $value) {
                    BotRunStatus::Success->value => 'green',
                    BotRunStatus::Failed->value => 'red',
                    BotRunStatus::Processing->value => 'blue',
                    default => 'gray',
                }),
            Date::make('Дата', 'created_at')
                ->format('d.m.Y H:i')
                ->sortable(),
        ];
    }

    protected function detailFields(): iterable
    {
        return [
            Preview::make()->changePreview(fn() => 
                \MoonShine\UI\Components\Alert::make(
                    icon: 'document-text',
                    type: 'info',
                )->content('<b>Логи ботов</b> — это журнал анализа рынка. Каждая запись показывает данные, на основе которых бот принимал решение в конкретный момент времени. Редактирование истории запрещено для обеспечения достоверности данных.')
            )->withoutWrapper(),

            ID::make(),
            BelongsTo::make(
                'Торговый бот',
                'bot',
                formatted: static fn (Bot $model): string => $model->name,
                resource: BotResource::class,
            ),
            Text::make('Торговая пара', 'symbol'),
            Number::make('Рыночная цена (на момент анализа)', 'market_price'),
            Enum::make('Сигнал стратегии', 'signal')
                ->attach(TradeSignal::class)
                ->badge(fn($value) => match($value?->value ?? $value) {
                    TradeSignal::Buy->value => 'green',
                    TradeSignal::Sell->value => 'red',
                    default => 'gray',
                }),
            Textarea::make('Причина решения / Текст ошибки', 'reason'),
            Json::make('Технические индикаторы (JSON)', 'indicators'),
            Enum::make('Статус выполнения', 'status')
                ->attach(BotRunStatus::class)
                ->badge(fn($value) => match($value?->value ?? $value) {
                    BotRunStatus::Success->value => 'green',
                    BotRunStatus::Failed->value => 'red',
                    BotRunStatus::Processing->value => 'blue',
                    default => 'gray',
                }),
            Date::make('Дата и время события', 'created_at')->format('d.m.Y H:i:s'),
        ];
    }

    protected function formFields(): iterable
    {
        return [];
    }

    protected function filters(): iterable
    {
        return [
            BelongsTo::make(
                'Бот',
                'bot',
                resource: BotResource::class,
            ),
            Text::make('Пара', 'symbol'),
            Enum::make('Сигнал', 'signal')
                ->attach(TradeSignal::class),
            Enum::make('Статус', 'status')
                ->attach(BotRunStatus::class),
        ];
    }
}
