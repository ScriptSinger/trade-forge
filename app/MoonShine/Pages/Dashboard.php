<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\Models\Bot;
use App\Models\Position;
use App\Models\Trade;
use App\Models\ExchangeAccount;
use App\Enums\PositionStatus;
use App\Enums\BotStatus;
use Illuminate\Support\Facades\Cache;
use MoonShine\Laravel\Pages\Page;
use MoonShine\UI\Components\Layout\Grid;
use MoonShine\UI\Components\Layout\Column;
use MoonShine\UI\Components\Metrics\Wrapped\ValueMetric;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Components\Heading;
use MoonShine\UI\Components\Alert;
use MoonShine\UI\Fields\Preview;
use MoonShine\Contracts\UI\ComponentContract;

class Dashboard extends Page
{
    public function getTitle(): string
    {
        return $this->title ?: 'Торговый Дашборд';
    }

    protected function components(): iterable
    {
        // 1. Статистика
        $activeBots = Bot::where('status', BotStatus::Active)->count();
        $openPositionsCount = Position::where('status', PositionStatus::Open)->count();
        $dailyProfit = Trade::whereDate('closed_at', now()->toDateString())->sum('profit_loss');

        // 2. Данные сканера
        $account = ExchangeAccount::first();
        $scannerData = $account ? Cache::get('bot_top_volatile_symbols_' . $account->id, []) : [];
        $scannerResults = collect($scannerData);

        // 3. Открытые позиции
        $openPositions = Position::with('bot')
            ->where('status', PositionStatus::Open)
            ->orderByDesc('opened_at')
            ->get();

        return [
            Grid::make([
                // Метрики
                Column::make([
                    ValueMetric::make('Активные боты')->value($activeBots),
                ])->columnSpan(2),

                Column::make([
                    ValueMetric::make('Позиции')->value($openPositionsCount),
                ])->columnSpan(2),

                Column::make([
                    ValueMetric::make('Монет в ТОП')->value($scannerResults->count()),
                ])->columnSpan(2),

                Column::make([
                    ValueMetric::make('Лидер')->value($scannerResults->first()['symbol'] ?? '---'),
                ])->columnSpan(3),

                Column::make([
                    ValueMetric::make('Профит дня')->value(number_format((float)$dailyProfit, 2) . ' $'),
                ])->columnSpan(3),

                // Секция позиций
                Column::make([
                    Heading::make('Открытые позиции')->tag('h3'),
                    TableBuilder::make()
                        ->items($openPositions)
                        ->fields([
                            Preview::make('Символ', 'symbol', fn($item) => "<b>{$item->symbol}</b>"),
                            Preview::make('Бот', 'bot.name'),
                            Preview::make('Вход', 'entry_price', fn($item) => number_format((float)$item->entry_price, 4)),
                            Preview::make('PnL %', 'pnl_pct', function($item) {
                                $color = $item->pnl_pct >= 0 ? '#28a745' : '#dc3545';
                                return "<span style='background:{$color}; color:white; padding:2px 6px; border-radius:4px; font-weight:bold'>" . 
                                       number_format((float)$item->pnl_pct, 2) . "%</span>";
                            }),
                            Preview::make('Объем', 'quantity'),
                            Preview::make('SL', 'sl', fn($item) => '<span style="color:red">'.number_format((float)$item->sl, 4).'</span>'),
                            Preview::make('TP', 'tp', fn($item) => '<span style="color:green">'.number_format((float)$item->tp, 4).'</span>'),
                            Preview::make('Открыта', 'opened_at', fn($item) => $item->opened_at?->diffForHumans() ?? '---'),
                        ]),
                ])->columnSpan(12),

                // Секция сканера
                Column::make([
                    Heading::make('Результаты сканирования (ТОП-30)')->tag('h3'),
                    $scannerResults->isEmpty() 
                        ? Alert::make(type: 'warning')->content('Сканер еще не собрал данные или кеш пуст.')
                        : TableBuilder::make()
                            ->items($scannerResults)
                            ->fields([
                                Preview::make('Символ', 'symbol', fn($item) => "<b>" . ($item['symbol'] ?? 'N/A') . "</b>"),
                                Preview::make('Волатильность (24ч)', 'volatility', fn($item) => 
                                    '<span style="background:#28a745; color:white; padding:2px 8px; border-radius:4px">' . 
                                    number_format((float)($item['volatility'] ?? 0), 2) . '%</span>'
                                ),
                                Preview::make('Объем (USDT)', 'volume', fn($item) => number_format((float)($item['volume'] ?? 0), 0, '.', ' ') . ' $'),
                                Preview::make('Цена', 'price', fn($item) => number_format((float)($item['price'] ?? 0), 4)),
                            ]),
                ])->columnSpan(12),
            ]),
        ];
    }
}
