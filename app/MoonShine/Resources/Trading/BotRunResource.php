<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Trading;

use App\Enums\BotRunStatus;
use App\Enums\TradeSignal;
use App\Models\Bot;
use App\Models\BotRun;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Support\Attributes\Icon;
use MoonShine\Support\Enums\Action;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\Alert;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Json;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Preview;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;

#[Icon('arrow-path')]
final class BotRunResource extends TradingResource
{
    protected string $model = BotRun::class;

    protected string $column = 'symbol';

    protected array $with = ['bot', 'order'];

    protected bool $isAsync = true;

    protected function activeActions(): ListOf
    {
        return parent::activeActions()
            ->except(Action::CREATE, Action::UPDATE)
            ->prepend(Action::VIEW);
    }

    public function getTitle(): string
    {
        return __('trading.resources.bot_runs');
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
            Number::make('Кол-во', 'quantity')->sortable(),
            Preview::make('Сумма USDT', 'quantity', function (BotRun $item) {
                $notional = $item->notionalUsdt();

                return $notional !== null ? number_format($notional, 2).' USDT' : '—';
            }),
            Text::make('Режим', 'mode'),
            Enum::make('Сигнал', 'signal')
                ->attach(TradeSignal::class)
                ->badge(fn ($value) => match ($value?->value ?? $value) {
                    TradeSignal::Buy->value => 'green',
                    TradeSignal::Sell->value => 'red',
                    default => 'gray',
                }),
            Enum::make('Статус', 'status')
                ->attach(BotRunStatus::class)
                ->badge(fn ($value) => match ($value?->value ?? $value) {
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
            Preview::make()->changePreview(
                fn () => Alert::make(
                    icon: 'document-text',
                    type: 'info',
                )->content('<b>Логи ботов</b> — журнал значимых событий: вход в позицию, выход, ошибки. Для исполненных сделок отображаются объём, сумма в USDT, SL/TP и связанный ордер.')
            )->withoutWrapper(),

            ID::make(),
            BelongsTo::make(
                'Торговый бот',
                'bot',
                formatted: static fn (Bot $model): string => $model->name,
                resource: BotResource::class,
            ),
            Text::make('Торговая пара', 'symbol'),
            Number::make('Рыночная цена (на момент сделки)', 'market_price'),
            Number::make('Количество (base asset)', 'quantity'),
            Preview::make('Сумма сделки (USDT)', 'quantity', function (BotRun $item) {
                $notional = $item->notionalUsdt();

                return $notional !== null ? number_format($notional, 2).' USDT' : '—';
            }),
            Text::make('Режим стратегии', 'mode'),
            Number::make('Stop Loss', 'stop_loss'),
            Number::make('Take Profit', 'take_profit'),
            BelongsTo::make(
                'Ордер',
                'order',
                resource: OrderResource::class,
            ),
            Enum::make('Сигнал стратегии', 'signal')
                ->attach(TradeSignal::class)
                ->badge(fn ($value) => match ($value?->value ?? $value) {
                    TradeSignal::Buy->value => 'green',
                    TradeSignal::Sell->value => 'red',
                    default => 'gray',
                }),
            Textarea::make('Причина решения / Текст ошибки', 'reason'),
            Json::make('Технические индикаторы (JSON)', 'indicators'),
            Enum::make('Статус выполнения', 'status')
                ->attach(BotRunStatus::class)
                ->badge(fn ($value) => match ($value?->value ?? $value) {
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
