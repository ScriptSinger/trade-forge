<?php

namespace App\Services\Position;

use App\Models\Bot;
use App\Models\Position;
use App\Enums\PositionStatus;
use App\Enums\TradeSignal;
use App\Services\Exchange\BybitExchangeService;
use App\Services\Bot\BotRunLogger;
use App\Events\MarketDataUpdated;
use Illuminate\Support\Facades\Log;

class PositionMonitorService
{
    public function __construct(
        private BybitExchangeService $exchange,
        private PositionService $positionService,
        private BotRunLogger $logger
    ) {}

    /**
     * Monitor all open positions for a bot.
     */
    public function monitor(Bot $bot): void
    {
        $positions = $bot->positions()->where('status', PositionStatus::Open)->get();

        if ($positions->isEmpty()) {
            return;
        }

        Log::info("Monitor: Checking " . $positions->count() . " open positions for bot #{$bot->id}");

        foreach ($positions as $position) {
            $this->checkPosition($position);
        }
        
        // Notify dashboard to refresh PnL
        broadcast(new MarketDataUpdated());
    }

    private function checkPosition(Position $position): void
    {
        $bot = $position->bot;
        $account = $bot->exchangeAccount;
        
        // 1. Get current market price
        $currentPrice = $this->exchange->getTicker($account, $position->symbol);
        
        if ($currentPrice <= 0) {
            return;
        }

        $entryPrice = (float) $position->entry_price;
        $pnlPct = (($currentPrice - $entryPrice) / $entryPrice) * 100;

        // Сохраняем актуальные данные для Дашборда
        $position->update([
            'current_price' => $currentPrice,
            'pnl_pct' => $pnlPct,
        ]);

        Log::info("Monitor: {$position->symbol} | Entry: {$entryPrice} | Now: {$currentPrice} | PnL: " . round($pnlPct, 2) . "%");

        // 2. Determine Strategy Mode (from bot strategy settings)
        $mode = $bot->strategy->settings['mode'] ?? 'Sniper';

        // 3. Exit Logic
        
        // A. Hard Stop Loss
        if ($position->sl > 0 && $currentPrice <= $position->sl) {
            $this->closePosition($position, $currentPrice, 'Stop Loss');
            return;
        }

        // B. Hard Take Profit (for Sniper mode)
        if ($mode === 'Sniper' && $position->tp > 0 && $currentPrice >= $position->tp) {
            $this->closePosition($position, $currentPrice, 'Take Profit');
            return;
        }

        // C. Hybrid Mode Logic (Trailing & Half-Sell)
        if ($mode === 'Hybrid') {
            $this->handleHybridLogic($position, $currentPrice);
        }
    }

    private function handleHybridLogic(Position $position, float $currentPrice): void
    {
        // If price reached TP but we only want to sell half first
        if (!$position->half_sold && $position->tp > 0 && $currentPrice >= $position->tp) {
            Log::info("Monitor: HYBRID - Target reached! Selling 50% and activating Trailing.");
            
            // 1. Sell half logic (Simplified: we mark as half sold and set BE)
            // In a real bot, we would send a partial sell order here.
            $position->update([
                'half_sold' => true,
                'be_activated' => true,
                'trailing_active' => true,
                'sl' => $position->entry_price * 1.0025, // Move SL to BE + 0.25%
            ]);

            $this->logger->info($position->bot, "HYBRID_HALF_SELL", ['price' => $currentPrice], $position->symbol);
            return;
        }

        // If Trailing is active, move SL up
        if ($position->trailing_active) {
            $trailingStep = 0.985; // 1.5% distance from peak
            $dynamicSl = $currentPrice * $trailingStep;

            if ($dynamicSl > $position->sl) {
                $position->update(['sl' => $dynamicSl]);
                Log::info("Monitor: {$position->symbol} - Trailing SL moved up to {$dynamicSl}");
            }
        }
    }

    private function closePosition(Position $position, float $price, string $reason): void
    {
        Log::info("Monitor: CLOSING {$position->symbol} due to {$reason} at {$price}");

        // 1. Send Sell Order to Exchange
        $orderResponse = $this->exchange->placeMarketOrder(
            account: $position->bot->exchangeAccount,
            symbol: $position->symbol,
            side: 'sell',
            qty: $position->quantity
        );

        // 2. Update Position status
        $position->update([
            'status' => PositionStatus::Closed,
            'exit_reason' => $reason,
            'closed_at' => now(),
        ]);

        // 3. Log results
        $this->logger->success($position->bot, TradeSignal::Sell, [
            'reason' => $reason,
            'price' => $price,
            'pnl' => $price - $position->entry_price
        ], $position->symbol);
    }
}
