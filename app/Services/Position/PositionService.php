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
use App\Services\Bot\DailyPerformanceService;
use App\Services\Bot\TradingLogger;
use App\Services\Exchange\BybitExchangeService;
use App\Services\Strategy\TechnicalIndicatorService;

class PositionService
{
    private const DUST_USDT = 1.0;

    public function __construct(
        private BybitExchangeService $exchange,
        private TradingLogger $log,
        private DailyPerformanceService $dailyPerformance,
        private TechnicalIndicatorService $indicators,
    ) {}

    public function monitor(Bot $bot, bool $sidewaysStop = false): void
    {
        $positions = $bot->positions()
            ->with(['bot.strategy.entrySettings', 'bot.strategy.riskSettings', 'bot.exchangeAccount'])
            ->where('status', PositionStatus::Open)
            ->get();

        if ($positions->isEmpty()) {
            return;
        }

        $this->log->orderDebug('Monitoring open positions', [
            'bot_id' => $bot->id,
            'count' => $positions->count(),
        ]);

        foreach ($positions as $position) {
            $runtimeMode = $this->resolveRuntimeMode($position);

            if ($sidewaysStop && $runtimeMode === 'Sniper') {
                $currentPrice = $this->exchange->getTicker($position->bot->exchangeAccount, $position->symbol);

                if ($currentPrice > 0) {
                    $this->executeExit($position, $currentPrice, 'Daily target in sideways', $runtimeMode);
                }

                continue;
            }

            $this->checkPosition($position, $runtimeMode);
        }
    }

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

    private function checkPosition(Position $position, string $runtimeMode): void
    {
        $currentPrice = $this->exchange->getTicker($position->bot->exchangeAccount, $position->symbol);

        if ($currentPrice <= 0) {
            return;
        }

        $entryPrice = (float) $position->entry_price;
        $pnlPct = (($currentPrice - $entryPrice) / $entryPrice) * 100;

        $position->update([
            'current_price' => $currentPrice,
            'pnl_pct' => $pnlPct,
        ]);

        if ($position->sl > 0 && $currentPrice <= $position->sl) {
            $reason = $runtimeMode === 'Hybrid' && $position->half_sold
                ? 'SL/Trailing (remainder)'
                : ($runtimeMode === 'Hybrid' ? 'SL/Trailing' : 'Stop Loss');

            $this->executeExit($position, $currentPrice, $reason, $runtimeMode);

            return;
        }

        if ($runtimeMode === 'Sniper') {
            if ($position->tp > 0 && $currentPrice >= $position->tp) {
                $this->executeExit($position, $currentPrice, 'Take Profit', $runtimeMode);
            }

            return;
        }

        $this->handleHybridLogic($position, $currentPrice, $runtimeMode);
    }

    private function handleHybridLogic(Position $position, float $currentPrice, string $runtimeMode): void
    {
        if (!$position->half_sold && $position->tp > 0 && $currentPrice >= $position->tp) {
            $sold = $this->executePartialExit($position, $currentPrice, 0.5, 'Take Profit (50%)', $runtimeMode);

            if (!$sold) {
                return;
            }

            $position->refresh();

            $position->update([
                'half_sold' => true,
                'be_activated' => true,
                'trailing_active' => true,
                'sl' => (float) $position->entry_price * 1.0025,
            ]);
        }

        if ($position->trailing_active) {
            $trailingPct = (float) ($position->bot->strategy->riskSettings?->trailing_pct ?? 1.5);
            $multiplier = (100 - $trailingPct) / 100;
            $dynamicSl = $currentPrice * $multiplier;

            if ($dynamicSl > (float) $position->sl) {
                $position->update(['sl' => $dynamicSl]);
            }
        }
    }

    private function executePartialExit(
        Position $position,
        float $price,
        float $portion,
        string $reason,
        string $runtimeMode,
    ): bool {
        $sellQty = (float) $position->quantity * $portion;

        if ($sellQty * $price < self::DUST_USDT) {
            $this->log->orderInfo('Dust position cleanup', [
                'bot_id' => $position->bot_id,
                'symbol' => $position->symbol,
            ]);
            $this->executeExit($position, $price, 'Dust cleanup', $runtimeMode);

            return false;
        }

        $this->log->orderInfo('Partial position close', [
            'bot_id' => $position->bot_id,
            'symbol' => $position->symbol,
            'reason' => $reason,
            'price' => $price,
            'portion' => $portion,
            'qty' => $sellQty,
        ]);

        $this->exchange->placeMarketOrder(
            $position->bot->exchangeAccount,
            $position->symbol,
            'sell',
            $sellQty,
        );

        $position->update([
            'quantity' => max(0, (float) $position->quantity - $sellQty),
        ]);

        $this->log->auditTradeEvent(
            $position->bot,
            'HYBRID_HALF_SELL',
            ['price' => $price, 'qty' => $sellQty, 'portion' => $portion],
            $position->symbol,
        );

        return true;
    }

    private function executeExit(Position $position, float $price, string $reason, ?string $runtimeMode = null): void
    {
        $mode = $runtimeMode ?? $this->resolveRuntimeMode($position);
        $quantity = (float) $position->quantity;

        if ($quantity * $price < self::DUST_USDT) {
            $position->update([
                'status' => PositionStatus::Closed,
                'exit_reason' => 'Dust cleanup',
                'closed_at' => now(),
            ]);

            return;
        }

        $this->log->orderInfo('Closing position', [
            'bot_id' => $position->bot_id,
            'symbol' => $position->symbol,
            'reason' => $reason,
            'price' => $price,
            'qty' => $quantity,
        ]);

        $this->exchange->placeMarketOrder(
            $position->bot->exchangeAccount,
            $position->symbol,
            'sell',
            $quantity,
        );

        $trade = Trade::create([
            'bot_id' => $position->bot_id,
            'symbol' => $position->symbol,
            'entry_price' => $position->entry_price,
            'exit_price' => $price,
            'quantity' => $quantity,
            'profit_loss' => ($price - $position->entry_price) * $quantity,
            'profit_percent' => (($price - $position->entry_price) / $position->entry_price) * 100,
            'opened_at' => $position->opened_at,
            'closed_at' => now(),
        ]);

        $this->dailyPerformance->recordClosedTrade($trade);

        $position->update([
            'status' => PositionStatus::Closed,
            'exit_reason' => $reason,
            'closed_at' => now(),
        ]);

        $this->log->auditPositionExit(
            bot: $position->bot,
            signal: TradeSignal::Sell,
            symbol: $position->symbol,
            price: $price,
            quantity: $quantity,
            reason: $reason,
            mode: $mode,
        );
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
        $position = $bot->positions()
            ->where('symbol', $order->symbol)
            ->where('status', PositionStatus::Open)
            ->first();

        if ($position) {
            $this->executeExit($position, (float) $order->price, 'Manual/External Sell');
        }
    }

    /**
     * Python STRATEGY_MODE 4: HYBRID if current ADX > threshold, else SNIPER.
     */
    private function resolveRuntimeMode(Position $position): string
    {
        $bot = $position->bot;
        $entrySettings = $bot->strategy->entrySettings;

        if (!$entrySettings) {
            return $position->mode ?: 'Sniper';
        }

        $threshold = (int) ($entrySettings->trend_adx_threshold ?? 30);
        $interval = (string) ($entrySettings->interval ?? 15);

        $rawCandles = $this->exchange->getKlines($bot->exchangeAccount, $position->symbol, $interval);

        if (empty($rawCandles)) {
            return $position->mode ?: 'Sniper';
        }

        $mappedCandles = array_map(fn (array $c) => [
            'ts' => $c[0],
            'open' => (float) $c[1],
            'high' => (float) $c[2],
            'low' => (float) $c[3],
            'close' => (float) $c[4],
            'vol' => (float) $c[5],
        ], array_reverse($rawCandles));

        $adxArr = $this->indicators->adx($mappedCandles, 14);
        $adx = end($adxArr) ?: 0;

        return $adx > $threshold ? 'Hybrid' : 'Sniper';
    }
}