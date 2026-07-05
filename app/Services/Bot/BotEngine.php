<?php

namespace App\Services\Bot;

use App\Enums\TradeContextStatus;
use App\Models\Bot;
use App\Services\Position\PositionService;
use App\Services\Bot\MarketScannerService;
use App\Services\Bot\Strategy\TradeContextFactory;
use App\Services\Bot\Strategy\TradeContext;
use App\Services\Bot\Strategy\StrategyPipeline;
use Illuminate\Support\Facades\Cache;

class BotEngine
{
    public function __construct(
        private PositionService $positions,
        private TradingLogger $log,
        private MarketScannerService $scanner,
        private StrategyPipeline $pipeline,
        private TradeContextFactory $contextFactory,
        private DailyPerformanceService $dailyPerformance,
        private SidewaysMarketGuard $sidewaysGuard,
    ) {}

    public function run(Bot $bot): void
    {
        $lock = Cache::lock("bot_run_{$bot->id}", 60);

        try {
            $lock->get(function () use ($bot) {
                $this->executeBotCycle($bot);
            });
        } catch (\Throwable $e) {
            $this->log->botError('Bot execution failed', [
                'bot_id' => $bot->id,
                'bot_name' => $bot->name,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private function executeBotCycle(Bot $bot): void
    {
        $cycleStartedAt = microtime(true);
        $stats = ['scanned' => 0, 'rejected' => 0, 'executed' => 0, 'failed' => 0];

        $this->log->botInfo('Bot cycle started', [
            'bot_id' => $bot->id,
            'bot_name' => $bot->name,
        ]);

        $account = $bot->exchangeAccount;

        if (!$account || $account->status->value !== 'active') {
            $this->log->botError('Exchange account is invalid', [
                'bot_id' => $bot->id,
                'account_id' => $account?->id,
            ]);

            $this->log->auditFailed($bot, 'INVALID_EXCHANGE_ACCOUNT');
            return;
        }

        $bot->loadMissing([
            'strategy.entrySettings',
            'strategy.riskSettings',
            'strategy.btcTrendFilter',
            'exchangeAccount',
        ]);

        if (!$bot->strategy?->entrySettings || !$bot->strategy?->riskSettings) {
            $this->log->botError('Strategy configuration incomplete', [
                'bot_id' => $bot->id,
                'strategy_id' => $bot->strategy_id,
                'has_entry_settings' => (bool) $bot->strategy?->entrySettings,
                'has_risk_settings' => (bool) $bot->strategy?->riskSettings,
            ]);
            $this->log->auditFailed($bot, 'MISSING_STRATEGY_SETTINGS');

            return;
        }

        $this->dailyPerformance->ensureTodayStat($bot);

        $sidewaysStop = $this->sidewaysGuard->blocksNewEntries($bot);

        $this->positions->monitor($bot, $sidewaysStop);

        if ($sidewaysStop) {
            $this->log->auditGuardEvent($bot, 'DAILY_TARGET_REACHED_SIDEWAYS', [
                'profit_pct' => round($this->dailyPerformance->profitPct($bot), 2),
            ]);

            $bot->forceFill(['last_run_at' => now()])->saveQuietly();

            $this->log->botInfo('Bot cycle stopped by SidewaysMarketGuard', [
                'bot_id' => $bot->id,
            ]);

            return;
        }

        $targets = $this->scanner->getTopVolatileSymbols($account);

        if (empty($targets)) {
            $this->log->botWarning('Scanner returned no symbols', [
                'bot_id' => $bot->id,
            ]);
            return;
        }

        $this->log->botInfo('Market scan completed', [
            'bot_id' => $bot->id,
            'symbols_found' => count($targets),
        ]);

        foreach ($targets as $target) {
            try {
                $this->processSymbol($bot, $target, $stats);

                if ($stats['executed'] > 0) {
                    break;
                }
            } catch (\Throwable $e) {
                $stats['failed']++;

                $this->log->botError('Symbol processing failed', [
                    'bot_id' => $bot->id,
                    'symbol' => $target['symbol'],
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        $bot->forceFill(['last_run_at' => now()])->saveQuietly();

        $this->log->botInfo('Bot cycle finished', [
            'bot_id' => $bot->id,
            'scanned' => $stats['scanned'],
            'rejected' => $stats['rejected'],
            'executed' => $stats['executed'],
            'failed' => $stats['failed'],
            'duration_ms' => (int) round((microtime(true) - $cycleStartedAt) * 1000),
        ]);
    }

    private function processSymbol(Bot $bot, array $target, array &$stats): void
    {
        $symbol = $target['symbol'];
        $stats['scanned']++;

        $this->log->botDebug('Processing symbol', [
            'bot_id' => $bot->id,
            'symbol' => $symbol,
            'volatility' => $target['volatility'],
        ]);

        $bot->strategy->loadMissing(['entrySettings', 'btcTrendFilter', 'riskSettings']);

        $context = $this->contextFactory->make($bot, $symbol);
        $result = $this->pipeline->run($context);

        $this->logFinalStatus($result, $stats);
    }

    private function logFinalStatus(TradeContext $context, array &$stats): void
    {
        $reason = $context->reason ?: 'Rejected by strategy filters';

        if ($context->isBlocked) {
            if ($context->status === TradeContextStatus::Failed) {
                $stats['failed']++;
                $this->log->auditFailed($context->bot, $reason, $context->indicators, $context->symbol);
            } else {
                $stats['rejected']++;
            }

            $this->log->strategyDebug('Symbol rejected', [
                'symbol' => $context->symbol,
                'status' => $context->status->value,
                'reason' => $reason,
            ]);

            return;
        }

        if ($context->status === TradeContextStatus::Executed) {
            $stats['executed']++;

            $this->log->orderInfo('Symbol executed', [
                'bot_id' => $context->bot->id,
                'symbol' => $context->symbol,
            ]);

            return;
        }

        $stats['rejected']++;

        $this->log->strategyDebug('Symbol finished without action', [
            'symbol' => $context->symbol,
            'reason' => 'Analysis finished: No entry signal',
        ]);
    }
}