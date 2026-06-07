<?php

namespace App\Services\Bot;

use App\Models\Bot;
use Illuminate\Support\Facades\Log;
use App\Services\Strategy\StrategyService;
use App\Services\Order\OrderService;
use App\Services\Risk\RiskService;
use App\Services\Position\PositionService;
use App\Services\Bot\BotRunLogger;
use App\Services\Exchange\BybitExchangeService;

class BotEngine
{
    public function __construct(
        private BybitExchangeService $exchange,
        private StrategyService $strategy,
        private RiskService $risk,
        private OrderService $orders,
        private PositionService $positions,
        private BotRunLogger $logger,
    ) {}

    public function run(Bot $bot): void
    {
        Log::info("BotEngine: Starting run for bot #{$bot->id} ({$bot->name})");

        /**
         * 0. Update last run time
         */
        $bot->update(['last_run_at' => now()]);

        /**
         * 1. Execution context (ВАЖНО)
         */
        $account = $bot->exchangeAccount;

        if (!$account || $account->status->value !== 'active') {
            Log::error("BotEngine: Invalid or inactive exchange account for bot #{$bot->id}");
            $this->logger->error($bot, 'INVALID_EXCHANGE_ACCOUNT');
            return;
        }

        $symbol = $bot->symbol;
        $interval = $bot->strategy->settings['interval'] ?? '1';

        Log::info("BotEngine: Fetching market data for {$symbol} ({$interval})");

        /**
         * 2. Market data
         */
        $market = $this->exchange->getMarketData($account, $symbol, $interval);

        $price = $market['price'] ?? null;
        $candles = $market['candles'] ?? [];

        Log::info("BotEngine: Market price is {$price}");

        if (!$price || empty($candles)) {
            Log::error("BotEngine: Empty market data for {$symbol}");
            $this->logger->error($bot, 'EMPTY_MARKET_DATA', $market);
            return;
        }

        /**
         * 3. Strategy
         */
        $signal = $this->strategy->execute($bot, $candles);

        Log::info("BotEngine: Strategy signal is: " . (is_string($signal) ? $signal : $signal->value));

        /**
         * 4. Log BEFORE execution (важно для трейдинга)
         */
        $this->logger->log($bot, $signal, $price, $candles);

        /**
         * 5. Risk check
         */
        if (!$this->risk->allowTrade($bot, $signal)) {
            Log::warning("BotEngine: Risk check FAILED for bot #{$bot->id}");
            $this->logger->info($bot, 'RISK_BLOCKED', [
                'signal' => $signal,
                'price' => $price,
            ]);

            return;
        }

        /**
         * 6. HOLD skip
         */
        if ($signal === \App\Enums\TradeSignal::Hold || (is_string($signal) && strtoupper($signal) === 'HOLD')) {
            Log::info("BotEngine: Signal is HOLD, skipping execution.");
            return;
        }

        /**
         * 7. Order execution (IMPORTANT: exchange account injected)
         */
        $qty = $this->risk->calculateSize($bot, $signal);

        if ($qty <= 0) {
            Log::info("BotEngine: Calculated quantity is 0 (nothing to sell), skipping order.");
            return;
        }

        Log::info("BotEngine: ATTEMPTING to place order: {$signal->value} {$qty} units");

        $orderResponse = $this->exchange->placeMarketOrder(
            account: $account,
            symbol: $symbol,
            side: is_string($signal) ? $signal : $signal->value,
            qty: $qty
        );

        Log::info("BotEngine: Exchange response: " . json_encode($orderResponse));

        /**
         * 8. Persist order
         */
        $order = $this->orders->storeFromResponse(
            bot: $bot,
            account: $account,
            symbol: $symbol,
            side: $signal,
            qty: $qty,
            response: $orderResponse,
            marketPrice: (float) $price
        );

        Log::info("BotEngine: Order stored with ID #{$order->id}, Status: {$order->status->value}");

        /**
         * 9. Position sync
         */
        $this->positions->syncFromOrder($bot, $order, $signal);

        Log::info("BotEngine: Position sync finished.");

        /**
         * 10. Final log
         */
        $this->logger->success($bot, $signal, [
            'price' => $price,
            'qty' => $qty,
            'order_id' => $order->id ?? null,
        ]);

        Log::info("BotEngine: Cycle finished successfully.");
    }
}
