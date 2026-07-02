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
use App\Services\Position\PositionMonitorService;
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
            Log::channel('bot')->error('Bot execution failed', [
                'bot_id' => $bot->id,
                'bot_name' => $bot->name,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private function executeBotCycle(Bot $bot): void
    {
        Log::channel('bot')->info('Bot cycle started', [
            'bot_id' => $bot->id,
            'bot_name' => $bot->name,
        ]);

        $account = $bot->exchangeAccount;

        if (!$account || $account->status->value !== 'active') {
            Log::channel('bot')->error('Exchange account is invalid', [
                'bot_id' => $bot->id,
                'account_id' => $account?->id,
            ]);

            $this->logger->error($bot, 'INVALID_EXCHANGE_ACCOUNT');
            return;
        }

        $this->positions->monitor($bot);
        $targets = $this->scanner->getTopVolatileSymbols($account);

        if (empty($targets)) {
            Log::channel('bot')->warning('Scanner returned no symbols', [
                'bot_id' => $bot->id,
            ]);
            return;
        }

        Log::channel('bot')->info('Market scan completed', [
            'bot_id' => $bot->id,
            'symbols_found' => count($targets),
        ]);


        foreach ($targets as $target) {
            // Пункт 4: Try-catch for each symbol
            try {
                $this->processSymbol($bot, $target);
            } catch (\Throwable $e) {
                Log::channel('bot')->error('Symbol processing failed', [
                    'bot_id' => $bot->id,
                    'symbol' => $target['symbol'],
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        // Пункт 6: Update last run time once at the end
        $bot->forceFill(['last_run_at' => now()])->saveQuietly();

        Log::channel('bot')->info('Bot cycle finished', [
            'bot_id' => $bot->id,
        ]);
    }

    private function processSymbol(Bot $bot, array $target): void
    {
        $symbol = $target['symbol'];

        Log::channel('bot')->debug('Processing symbol', [
            'bot_id' => $bot->id,
            'symbol' => $symbol,
            'volatility' => $target['volatility'],
        ]);

        $account = $bot->exchangeAccount;
        $interval = $bot->strategy->settings['interval'] ?? '15';

        /**
         * 2. Market data for the symbol
         */
        $market = $this->exchange->getMarketData($account, $symbol, $interval);
        $price = $market['price'] ?? null;
        $candles = $market['candles'] ?? [];

        if (!$price || empty($candles)) {
            Log::channel('bot')->warning('Market data unavailable', [
                'bot_id' => $bot->id,
                'symbol' => $symbol,
            ]);
            return;
        }

        $btcMarket = $this->exchange->getMarketData($account, 'BTCUSDT', '60');


        $context = new TradeContext(
            bot: $bot,
            symbol: $symbol,
            candles: $candles,
            btcCandles: $btcMarket['candles'] ?? []
        );

        // Run the encapsulated pipeline
        $result = $this->pipeline->run($context);

        $this->logFinalStatus($result);
    }

    private function logFinalStatus(TradeContext $context): void
    {
        $lastCandle = $context->lastCandle();
        $price = $lastCandle['close'] ?? null;

        if ($context->isBlocked) {
            // Если причина пустая, подставим дефолтную, чтобы не было пустых полей в MoonShine
            $reason = $context->reason ?: 'Rejected by strategy filters';

            $this->logger->success(
                $context->bot,
                \App\Enums\TradeSignal::Hold,
                $context->indicators,
                $context->symbol,
                $price,
                $reason
            );

            Log::info("BotEngine: Symbol {$context->symbol} rejected [{$context->status->value}]. Reason: {$reason}");
        } elseif ($context->status === \App\Enums\TradeContextStatus::Executed) {
            Log::info("BotEngine: Symbol {$context->symbol} processed successfully. Order placed.");
        } else {
            // Случай, когда пайплайн завершился без блокировки, но и без ордера (редкий случай)
            $this->logger->success(
                $context->bot,
                \App\Enums\TradeSignal::Hold,
                $context->indicators,
                $context->symbol,
                $price,
                'Analysis finished: No entry signal'
            );
            Log::info("BotEngine: Symbol {$context->symbol} finished without action.");
        }
    }
}
