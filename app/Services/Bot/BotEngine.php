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

use App\Services\Bot\MarketScannerService;
use App\Services\Bot\Strategy\TradeContext;
use Illuminate\Support\Facades\Pipeline;

use App\Services\Bot\Strategy\StrategyPipeline;
use Illuminate\Support\Facades\Cache;

class BotEngine
{
    public function __construct(
        private BybitExchangeService $exchange,
        private StrategyService $strategy,
        private RiskService $risk,
        private OrderService $orders,
        private PositionService $positions,
        private BotRunLogger $logger,
        private MarketScannerService $scanner,
        private StrategyPipeline $pipeline,
    ) {}

    public function run(Bot $bot): void
    {
        // Пункт 7: Distributed Lock to prevent concurrent runs
        $lock = Cache::lock("bot_run_{$bot->id}", 60);

        try {
            $lock->get(function () use ($bot) {
                $this->executeBotCycle($bot);
            });
        } catch (\Throwable $e) {
            Log::error("BotEngine: Lock failed or critical error for bot #{$bot->id}: {$e->getMessage()}");
        }
    }

    private function executeBotCycle(Bot $bot): void
    {
        Log::info("BotEngine: Starting run for bot #{$bot->id} ({$bot->name})");

        $account = $bot->exchangeAccount;

        if (!$account || $account->status->value !== 'active') {
            Log::error("BotEngine: Invalid or inactive exchange account for bot #{$bot->id}");
            $this->logger->error($bot, 'INVALID_EXCHANGE_ACCOUNT');
            return;
        }

        // ШАГ 1: Сканирование рынка
        $targets = $this->scanner->getTopVolatileSymbols($account);

        if (empty($targets)) {
            Log::warning("BotEngine: No symbols found during scan.");
            return;
        }

        Log::info("BotEngine: Market Scan Results for Bot #{$bot->id}:");
        foreach ($targets as $target) {
            // Пункт 4: Try-catch for each symbol
            try {
                $this->processSymbol($bot, $target);
            } catch (\Throwable $e) {
                Log::error("BotEngine: Failed to process {$target['symbol']}: {$e->getMessage()}");
            }
        }

        // Пункт 6: Update last run time once at the end
        $bot->forceFill(['last_run_at' => now()])->saveQuietly();
        
        Log::info("BotEngine: Cycle finished successfully.");
    }

    private function processSymbol(Bot $bot, array $target): void
    {
        $symbol = $target['symbol'];
        Log::info("BotEngine: Processing symbol {$symbol} (Vol: {$target['volatility']}%)");

        $account = $bot->exchangeAccount;
        $interval = $bot->strategy->settings['interval'] ?? '15'; 

        /**
         * 2. Market data for the symbol
         */
        $market = $this->exchange->getMarketData($account, $symbol, $interval);
        $price = $market['price'] ?? null;
        $candles = $market['candles'] ?? [];

        if (!$price || empty($candles)) {
            return;
        }

        $context = new TradeContext($bot, $symbol, $candles);

        // Run the encapsulated pipeline
        $result = $this->pipeline->run($context);

        $this->logFinalStatus($result);
    }

    private function logFinalStatus(TradeContext $context): void
    {
        if ($context->isBlocked) {
            Log::info("BotEngine: Symbol {$context->symbol} rejected [{$context->status->value}]. Reason: {$context->reason}");
        } elseif ($context->status === \App\Enums\TradeContextStatus::Executed) {
            Log::info("BotEngine: Symbol {$context->symbol} processed successfully. Order placed.");
        } else {
            Log::info("BotEngine: Symbol {$context->symbol} finished with status: {$context->status->value}");
        }
    }
}
