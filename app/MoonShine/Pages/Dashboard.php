<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\Enums\BotStatus;
use App\Enums\PositionStatus;
use App\Models\Bot;
use App\Models\Position;
use App\Models\Trade;
use MoonShine\Laravel\Pages\Page;
use MoonShine\Support\Attributes\Icon;
use MoonShine\UI\Components\Heading;
use MoonShine\UI\Components\Layout\Column;
use MoonShine\UI\Components\Layout\Grid;
use MoonShine\UI\Components\Metrics\Wrapped\ValueMetric;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Fields\Preview;

#[Icon('home')]
class Dashboard extends Page
{
    public function getTitle(): string
    {
        return $this->title ?: __('trading.dashboard.title');
    }

    protected function components(): iterable
    {
        // 1. Статистика
        $activeBots = Bot::where('status', BotStatus::Active)->count();

        $openPositionsCount = Position::where('status', PositionStatus::Open)->count();

        $dailyProfit = Trade::whereDate('closed_at', now()->toDateString())
            ->sum('profit_loss');

        // 2. Открытые позиции
        $openPositions = Position::with('bot')
            ->where('status', PositionStatus::Open)
            ->orderByDesc('opened_at')
            ->get();

        return [
            Grid::make([
                Column::make([
                    ValueMetric::make(__('trading.dashboard.active_bots'))->value($activeBots),
                ])->columnSpan(3),

                Column::make([
                    ValueMetric::make(__('trading.dashboard.open_positions'))->value($openPositionsCount),
                ])->columnSpan(3),

                Column::make([
                    ValueMetric::make(__('trading.dashboard.daily_profit'))->value(
                        number_format((float) $dailyProfit, 2).' $'
                    ),
                ])->columnSpan(3),
            ]),

            Grid::make([
                Column::make([
                    Heading::make(__('trading.dashboard.open_positions_heading'))->tag('h3'),

                    TableBuilder::make()
                        ->items($openPositions)
                        ->fields([
                            Preview::make(__('trading.dashboard.symbol'), 'symbol', fn ($item) => "<b>{$item->symbol}</b>"),
                            Preview::make(__('trading.dashboard.bot'), 'bot.name'),
                            Preview::make(__('trading.dashboard.entry'), 'entry_price', fn ($item) => number_format((float) $item->entry_price, 4)),

                            Preview::make('PnL %', 'pnl_pct', function ($item) {
                                $color = $item->pnl_pct >= 0 ? '#28a745' : '#dc3545';

                                return "<span style='background:{$color}; color:white; padding:2px 6px; border-radius:4px; font-weight:bold'>"
                                    .number_format((float) $item->pnl_pct, 2).'%</span>';
                            }),

                            Preview::make(__('trading.dashboard.volume'), 'quantity'),

                            Preview::make(
                                'SL',
                                'sl',
                                fn ($item) => '<span style="color:red">'.number_format((float) $item->sl, 4).'</span>'
                            ),

                            Preview::make(
                                'TP',
                                'tp',
                                fn ($item) => '<span style="color:green">'.number_format((float) $item->tp, 4).'</span>'
                            ),

                            Preview::make(
                                __('trading.dashboard.opened_at'),
                                'opened_at',
                                fn ($item) => $item->opened_at?->diffForHumans() ?? '---'
                            ),
                        ]),
                ])->columnSpan(12),
            ]),
        ];
    }
}
