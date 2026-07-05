<?php

namespace App\Services\Position;

use App\Models\Bot;
use App\Models\Order;
use App\Models\Position;
use App\Models\Trade;
use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Enums\PositionStatus;
use App\Enums\TradeSignal;
use App\Services\Bot\BotRunLogger;
use App\Services\Bot\DailyPerformanceService;
use App\Services\Exchange\BybitExchangeService;
use Illuminate\Support\Facades\Log;

class PositionService
{
    public function __construct(
        private BybitExchangeService $exchange,
        private BotRunLogger $logger,
        private DailyPerformanceService $dailyPerformance,
    ) {}

    /**
     * ШАГ 0: Мониторинг всех открытых позиций бота.
     */
    public function monitor(Bot $bot, bool $sidewaysStop = false): void
    {
        $positions = $bot->positions()
            ->with(['bot.strategy.riskSettings', 'bot.exchangeAccount'])
            ->where('status', PositionStatus::Open)
            ->get();

        if ($positions->isEmpty()) {
            return;
        }

        Log::info("PositionService: Checking " . $positions->count() . " positions for bot #{$bot->id}");

        foreach ($positions as $position) {
            if ($sidewaysStop && $this->isSniperMode($position)) {
                $currentPrice = $this->exchange->getTicker($position->bot->exchangeAccount, $position->symbol);

                if ($currentPrice > 0) {
                    $this->executeExit($position, $currentPrice, 'Daily target in sideways');
                }

                continue;
            }

            $this->checkPosition($position);
        }
    }

    /**
     * Синхронизация после исполнения ордера на бирже.
     */
    public function syncFromOrder(
        Bot $bot,
        Order $order,
        TradeSignal|string $signal,
        string $mode = 'Sniper',
        ?float $sl = null,
        ?float $tp = null,
    ): void {
        if ($order->status !== OrderStatus::Filled) {
            return;
        }

        if ($order->side === OrderSide::Buy) {
            $this->openPosition($bot, $order, $mode, $sl, $tp);
        } elseif ($order->side === OrderSide::Sell) {
            $this->closePositionFromOrder($bot, $order);
        }
    }

    private function checkPosition(Position $position): void
    {
        $currentPrice = $this->exchange->getTicker($position->bot->exchangeAccount, $position->symbol);
        
        if ($currentPrice <= 0) return;

        $entryPrice = (float) $position->entry_price;
        $pnlPct = (($currentPrice - $entryPrice) / $entryPrice) * 100;

        // Обновляем данные для Дашборда
        $position->update([
            'current_price' => $currentPrice,
            'pnl_pct' => $pnlPct,
        ]);

        $mode = $this->resolvePositionMode($position);

        // Логика выхода (SL/TP)
        if ($position->sl > 0 && $currentPrice <= $position->sl) {
            $this->executeExit($position, $currentPrice, 'Stop Loss');
        } elseif ($mode === 'Sniper' && $position->tp > 0 && $currentPrice >= $position->tp) {
            $this->executeExit($position, $currentPrice, 'Take Profit');
        } elseif ($mode === 'Hybrid') {
            $this->handleHybridLogic($position, $currentPrice);
        }
    }

    private function handleHybridLogic(Position $position, float $currentPrice): void
    {
        // Продажа 50% и активация Трейлинга
        if (!$position->half_sold && $position->tp > 0 && $currentPrice >= $position->tp) {
            $position->update([
                'half_sold' => true,
                'be_activated' => true,
                'trailing_active' => true,
                'sl' => $position->entry_price * 1.0025,
            ]);
            $this->logger->info($position->bot, "HYBRID_HALF_SELL", ['price' => $currentPrice], $position->symbol);
        }

        // Подтяжка стопа (Трейлинг)
        if ($position->trailing_active) {
            $trailingPct = (float) ($position->bot->strategy->riskSettings?->trailing_pct ?? 1.5);
            $multiplier = (100 - $trailingPct) / 100;
            
            $dynamicSl = $currentPrice * $multiplier; 
            
            if ($dynamicSl > $position->sl) {
                $position->update(['sl' => $dynamicSl]);
            }
        }
    }

    private function executeExit(Position $position, float $price, string $reason): void
    {
        Log::info("PositionService: CLOSING {$position->symbol} ({$reason}) at {$price}");

        $this->exchange->placeMarketOrder($position->bot->exchangeAccount, $position->symbol, 'sell', $position->quantity);

        $trade = Trade::create([
            'bot_id' => $position->bot_id,
            'symbol' => $position->symbol,
            'entry_price' => $position->entry_price,
            'exit_price' => $price,
            'quantity' => $position->quantity,
            'profit_loss' => ($price - $position->entry_price) * $position->quantity,
            'profit_percent' => (($price - $position->entry_price) / $position->entry_price) * 100,
            'opened_at' => $position->opened_at,
            'closed_at' => now(),
        ]);

        $this->dailyPerformance->recordClosedTrade($trade);

        $position->update(['status' => PositionStatus::Closed, 'exit_reason' => $reason, 'closed_at' => now()]);
        $this->logger->success($position->bot, TradeSignal::Sell, ['reason' => $reason, 'price' => $price], $position->symbol);
    }

    private function openPosition(
        Bot $bot,
        Order $order,
        string $mode = 'Sniper',
        ?float $sl = null,
        ?float $tp = null,
    ): void {
        Position::create([
            'bot_id' => $bot->id,
            'symbol' => $order->symbol,
            'mode' => $mode,
            'entry_price' => (float) $order->price,
            'quantity' => (float) $order->quantity,
            'sl' => $sl,
            'tp' => $tp,
            'status' => PositionStatus::Open,
            'opened_at' => now(),
        ]);
    }

    private function closePositionFromOrder(Bot $bot, Order $order): void
    {
        $position = $bot->positions()->where('symbol', $order->symbol)->where('status', PositionStatus::Open)->first();
        if ($position) {
            $this->executeExit($position, (float)$order->price, 'Manual/External Sell');
        }
    }

    private function isSniperMode(Position $position): bool
    {
        return $this->resolvePositionMode($position) === 'Sniper';
    }

    private function resolvePositionMode(Position $position): string
    {
        return $position->mode ?: 'Sniper';
    }
}
