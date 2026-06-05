<?php

namespace App\Services\Bot;

use App\Models\Bot;

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
        /**
         * 0. Update last run time
         * We use update() to ensure only this field is changed, 
         * preserving existing JSON settings.
         */
        $bot->update(['last_run_at' => now()]);

        /**
         * 1. Execution context (ВАЖНО)
         */
        $account = $bot->exchangeAccount;

        if (!$account || $account->status->value !== 'active') {
            $this->logger->error($bot, 'INVALID_EXCHANGE_ACCOUNT');
            return;
        }

        $symbol = $bot->symbol;
        $interval = $bot->strategy->settings['interval'] ?? '1';

        /**
         * 2. Market data
         */
        $market = $this->exchange->getMarketData($symbol, $interval);


        $price = $market['price'] ?? null;
        $candles = $market['candles'] ?? [];


        if (!$price || empty($candles)) {
            $this->logger->error($bot, 'EMPTY_MARKET_DATA', $market);
            return;
        }

        /**
         * 3. Strategy
         */
        $signal = $this->strategy->execute($bot, $candles);

        /**
         * 4. Log BEFORE execution (важно для трейдинга)
         */
        $this->logger->log($bot, $signal, $price, $candles);

        /**
         * 5. Risk check
         */
        if (!$this->risk->allowTrade($bot, $signal)) {
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
            return;
        }

        /**
         * 7. Order execution (IMPORTANT: exchange account injected)
         */
        $qty = $this->risk->calculateSize($bot);

        $orderResponse = $this->exchange->placeMarketOrder(
            account: $account,
            symbol: $symbol,
            side: is_string($signal) ? $signal : $signal->value,
            qty: $qty
        );

        /**
         * 8. Persist order
         */
        $order = $this->orders->storeFromResponse(
            bot: $bot,
            account: $account,
            symbol: $symbol,
            side: $signal,
            qty: $qty,
            response: $orderResponse
        );

        /**
         * 9. Position sync
         */
        $this->positions->syncFromOrder($bot, $order, $signal);

        /**
         * 10. Final log
         */
        $this->logger->success($bot, $signal, [
            'price' => $price,
            'qty' => $qty,
            'order_id' => $order->id ?? null,
        ]);
    }
}
