<?php

namespace App\Services\Bot;

use App\Models\Bot;
use Illuminate\Support\Facades\Log;
use App\Services\Position\PositionService;
use App\Services\Bot\BotRunLogger;
use App\Services\Bot\MarketScannerService;
use App\Services\Bot\Strategy\TradeContextFactory;
use App\Services\Bot\Strategy\TradeContext;
use App\Services\Bot\Strategy\StrategyPipeline;
use Illuminate\Support\Facades\Cache;

class BotEngine
{
    public function __construct(
        private PositionService $positions,
        private BotRunLogger $logger,
        private MarketScannerService $scanner,
        private StrategyPipeline $pipeline,
        private TradeContextFactory $contextFactory,
        private DailyPerformanceService $dailyPerformance,
        private SidewaysMarketGuard $sidewaysGuard,
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

        $bot->loadMissing([
            'strategy.riskSettings',
            'strategy.btcTrendFilter',
            'exchangeAccount',
        ]);

        $this->dailyPerformance->ensureTodayStat($bot);

        $sidewaysStop = $this->sidewaysGuard->blocksNewEntries($bot);

        $this->positions->monitor($bot, $sidewaysStop);

        if ($sidewaysStop) {
            $this->logger->info($bot, 'DAILY_TARGET_REACHED_SIDEWAYS', [
                'profit_pct' => round($this->dailyPerformance->profitPct($bot), 2),
            ]);

            $bot->forceFill(['last_run_at' => now()])->saveQuietly();

            Log::channel('bot')->info('Bot cycle stopped by SidewaysMarketGuard', [
                'bot_id' => $bot->id,
            ]);

            return;
        }

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

        // Load settings
        $bot->strategy->loadMissing(['entrySettings', 'btcTrendFilter', 'riskSettings']);

        // Use the factory to create the context
        $context = $this->contextFactory->make($bot, $symbol);

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
