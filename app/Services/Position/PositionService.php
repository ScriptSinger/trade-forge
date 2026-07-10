<?php

namespace App\Services\Position;

use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Enums\PositionStatus;
use App\Enums\TradeSignal;
use App\Models\Bot;
use App\Models\Order;
use App\Models\Position;
use App\Models\Trade;
use App\Services\Bot\Engine\TradingLogger;
use App\Services\Bot\Performance\DailyPerformanceService;
use App\Services\Bot\Performance\TradePnlCalculator;
use App\Services\Bot\Strategy\StrategyModeResolver;
use App\Services\Exchange\Bybit\BybitExchangeService;
use App\Services\Notifications\TradeTelegramNotifier;
use App\Services\Strategy\TechnicalIndicatorService;

class PositionService
{
    private const DUST_USDT = 1.0;

    public function __construct(
        private BybitExchangeService $exchange,
        private TradingLogger $log,
        private DailyPerformanceService $dailyPerformance,
        private TechnicalIndicatorService $indicators,
        private TradePnlCalculator $pnlCalculator,
        private TradeTelegramNotifier $tradeTelegram,
        private StrategyModeResolver $strategyModeResolver,
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

        if ($runtimeMode === 'Surfer') {
            $this->handleSurferLogic($position, $currentPrice);
            $position->refresh();
        } elseif ($runtimeMode === 'Hybrid') {
            $this->handleHybridLogic($position, $currentPrice, $runtimeMode);
            $position->refresh();
        }

        if ($position->sl > 0 && $currentPrice <= $position->sl) {
            $reason = match ($runtimeMode) {
                'Hybrid' => $position->half_sold ? 'SL/Trailing (remainder)' : 'SL/Trailing',
                'Surfer' => 'SL/Trailing',
                default => 'Stop Loss',
            };

            $this->executeExit($position, $currentPrice, $reason, $runtimeMode);

            return;
        }

        if ($runtimeMode === 'Sniper' && $position->tp > 0 && $currentPrice >= $position->tp) {
            $this->executeExit($position, $currentPrice, 'Take Profit', $runtimeMode);
        }
    }

    private function handleSurferLogic(Position $position, float $currentPrice): void
    {
        $entry = (float) $position->entry_price;
        $tp = (float) $position->tp;

        if ($tp <= $entry) {
            return;
        }

        $activation = $entry + ($tp - $entry) * 0.8;

        if ($currentPrice >= $activation && ! $position->trailing_active) {
            $position->update(['trailing_active' => true]);

            $this->tradeTelegram->notifySurferActivation($position->symbol);

            $this->log->orderInfo('Surfer trailing activated', [
                'bot_id' => $position->bot_id,
                'symbol' => $position->symbol,
                'price' => $currentPrice,
                'activation' => $activation,
            ]);
        }

        $this->updateTrailingStop($position, $currentPrice);
    }

    private function handleHybridLogic(Position $position, float $currentPrice, string $runtimeMode): void
    {
        if (! $position->half_sold && $position->tp > 0 && $currentPrice >= $position->tp) {
            $sold = $this->executePartialExit($position, $currentPrice, 0.5, 'Take Profit (50%)', $runtimeMode);

            if (! $sold) {
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

        $this->updateTrailingStop($position, $currentPrice);
    }

    private function updateTrailingStop(Position $position, float $currentPrice): void
    {
        if (! $position->trailing_active) {
            return;
        }

        $trailingPct = (float) ($position->bot->strategy->riskSettings?->trailing_pct ?? 1.5);
        $multiplier = (100 - $trailingPct) / 100;
        $dynamicSl = $currentPrice * $multiplier;

        if ($dynamicSl > (float) $position->sl) {
            $position->update(['sl' => $dynamicSl]);
        }
    }

    private function executePartialExit(
        Position $position,
        float $price,
        float $portion,
        string $reason,
        string $runtimeMode,
    ): bool {
        $resolved = $this->resolveSellQuantity($position, $portion, $price);

        if ($resolved['is_dust']) {
            $this->log->orderInfo('Dust position cleanup', [
                'bot_id' => $position->bot_id,
                'symbol' => $position->symbol,
            ]);
            $this->closeDustPosition($position);

            return false;
        }

        $normalizedQty = $resolved['qty'];

        $this->log->orderInfo('Partial position close', [
            'bot_id' => $position->bot_id,
            'symbol' => $position->symbol,
            'reason' => $reason,
            'price' => $price,
            'portion' => $portion,
            'qty' => $normalizedQty,
        ]);

        $this->exchange->placeMarketOrder(
            $position->bot->exchangeAccount,
            $position->symbol,
            'sell',
            $normalizedQty,
        );

        $pnl = $this->pnlCalculator->calculate(
            (float) $position->entry_price,
            $price,
            $normalizedQty,
            $this->spotFeeRate($position),
        );

        $this->dailyPerformance->recordPartialPnl(
            $position->bot,
            $pnl['profit_loss'],
            $pnl['fees'],
        );

        $position->update([
            'quantity' => max(0, (float) $position->quantity - $normalizedQty),
        ]);

        $this->tradeTelegram->notifyExit(
            symbol: $position->symbol,
            reason: $reason,
            portion: $portion,
            pnlPct: $pnl['profit_percent'],
            profitUsdt: $pnl['profit_loss'],
        );

        $this->log->auditTradeEvent(
            $position->bot,
            'HYBRID_HALF_SELL',
            ['price' => $price, 'qty' => $normalizedQty, 'portion' => $portion],
            $position->symbol,
        );

        return true;
    }

    private function executeExit(Position $position, float $price, string $reason, ?string $runtimeMode = null): void
    {
        $mode = $runtimeMode ?? $this->resolveRuntimeMode($position);
        $resolved = $this->resolveSellQuantity($position, 1.0, $price);

        if ($resolved['is_dust']) {
            $this->closeDustPosition($position);

            return;
        }

        $normalizedQty = $resolved['qty'];

        $this->log->orderInfo('Closing position', [
            'bot_id' => $position->bot_id,
            'symbol' => $position->symbol,
            'reason' => $reason,
            'price' => $price,
            'qty' => $normalizedQty,
        ]);

        $this->exchange->placeMarketOrder(
            $position->bot->exchangeAccount,
            $position->symbol,
            'sell',
            $normalizedQty,
        );

        $pnl = $this->pnlCalculator->calculate(
            (float) $position->entry_price,
            $price,
            $normalizedQty,
            $this->spotFeeRate($position),
        );

        $trade = Trade::create([
            'bot_id' => $position->bot_id,
            'symbol' => $position->symbol,
            'entry_price' => $position->entry_price,
            'exit_price' => $price,
            'quantity' => $normalizedQty,
            'profit_loss' => $pnl['profit_loss'],
            'profit_percent' => $pnl['profit_percent'],
            'fees' => $pnl['fees'],
            'opened_at' => $position->opened_at,
            'closed_at' => now(),
        ]);

        $this->dailyPerformance->recordClosedTrade($trade);

        $position->update([
            'status' => PositionStatus::Closed,
            'exit_reason' => $reason,
            'closed_at' => now(),
        ]);

        $this->tradeTelegram->notifyExit(
            symbol: $position->symbol,
            reason: $reason,
            portion: 1.0,
            pnlPct: $pnl['profit_percent'],
            profitUsdt: $pnl['profit_loss'],
        );

        $this->log->auditPositionExit(
            bot: $position->bot,
            signal: TradeSignal::Sell,
            symbol: $position->symbol,
            price: $price,
            quantity: $normalizedQty,
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
     * Python STRATEGY_MODE 1–4: runtime SURFER / HYBRID / SNIPER by ADX.
     */
    private function resolveRuntimeMode(Position $position): string
    {
        $bot = $position->bot;
        $entrySettings = $bot->strategy->entrySettings;

        if (! $entrySettings) {
            return $position->mode ?: 'Sniper';
        }

        $strategyMode = $this->strategyModeResolver->fromSettings($entrySettings);
        $threshold = (int) ($entrySettings->trend_adx_threshold ?? 30);
        $interval = (string) ($entrySettings->interval ?? 15);

        $klineLimit = (int) (
            $entrySettings->kline_limit
            ?? BybitExchangeService::DEFAULT_KLINE_LIMIT
        );

        $rawCandles = $this->exchange->getKlines(
            $bot->exchangeAccount,
            $position->symbol,
            $interval,
            $klineLimit,
        );

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

        return $this->strategyModeResolver->resolveRuntime($strategyMode, $adx, $threshold);
    }

    private function spotFeeRate(Position $position): float
    {
        return (float) ($position->bot->strategy->riskSettings?->spot_fee_rate ?? 0.001);
    }

    /**
     * Python sample sell(): partial uses stored qty × portion, full uses exchange free balance.
     *
     * @return array{qty: float, is_dust: bool}
     */
    private function resolveSellQuantity(Position $position, float $portion, float $price): array
    {
        $account = $position->bot->exchangeAccount;
        $baseCoin = $this->baseCoinFromSymbol($position->symbol);
        $actualQty = (float) ($this->exchange->getAccountBalance($account, $baseCoin)?->free ?? 0.0);

        $storedQty = (float) $position->quantity;
        $targetQty = $portion >= 1.0
            ? $actualQty
            : $storedQty * $portion;

        if ($targetQty * $price < self::DUST_USDT) {
            return ['qty' => 0.0, 'is_dust' => true];
        }

        $sellQty = min($targetQty, $actualQty);
        $normalizedQty = $this->exchange->normalizeQuantity($account, $position->symbol, $sellQty);

        if ($normalizedQty <= 0) {
            return ['qty' => 0.0, 'is_dust' => true];
        }

        return ['qty' => $normalizedQty, 'is_dust' => false];
    }

    private function closeDustPosition(Position $position): void
    {
        $position->update([
            'status' => PositionStatus::Closed,
            'exit_reason' => 'Dust cleanup',
            'closed_at' => now(),
        ]);
    }

    private function baseCoinFromSymbol(string $symbol): string
    {
        return str_ends_with($symbol, 'USDT')
            ? substr($symbol, 0, -4)
            : $symbol;
    }
}
