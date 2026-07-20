<?php

namespace App\Services\Bot\Engine;

use App\Enums\ExchangeAccountStatus;
use App\Enums\PositionStatus;
use App\Enums\TradeContextStatus;
use App\Models\Bot;
use App\Services\Bot\Guards\SidewaysMarketGuard;
use App\Services\Bot\Market\MarketScannerService;
use App\Services\Bot\Performance\DailyPerformanceService;
use App\Services\Bot\Strategy\StrategyPipeline;
use App\Services\Bot\Strategy\TradeContext;
use App\Services\Bot\Strategy\TradeContextFactory;
use App\Services\Position\PositionService;
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
            $acquired = $lock->get(function () use ($bot) {
                $this->executeBotCycle($bot);
            });

            if (! $acquired) {
                // Normal under concurrent scheduler ticks — avoid flooding bot logs.
                $this->log->botDebug('Bot cycle skipped: lock held', [
                    'bot_id' => $bot->id,
                    'bot_name' => $bot->name,
                ]);
            }
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
        $stopReason = 'completed';

        $this->log->botInfo('Bot cycle started', [
            'bot_id' => $bot->id,
            'bot_name' => $bot->name,
        ]);

        try {
            $account = $bot->exchangeAccount;

            if (! $account || $account->status !== ExchangeAccountStatus::Active) {
                $this->log->botError('Exchange account is invalid', [
                    'bot_id' => $bot->id,
                    'account_id' => $account?->id,
                ]);

                $this->log->auditFailed($bot, 'INVALID_EXCHANGE_ACCOUNT');
                $stopReason = 'invalid_account';

                return;
            }

            $bot->loadMissing([
                'strategy.entrySettings',
                'strategy.riskSettings',
                'strategy.btcTrendFilter',
                'exchangeAccount',
            ]);

            if (! $bot->strategy?->entrySettings || ! $bot->strategy?->riskSettings) {
                $this->log->botError('Strategy configuration incomplete', [
                    'bot_id' => $bot->id,
                    'strategy_id' => $bot->strategy_id,
                    'has_entry_settings' => (bool) $bot->strategy?->entrySettings,
                    'has_risk_settings' => (bool) $bot->strategy?->riskSettings,
                ]);
                $this->log->auditFailed($bot, 'MISSING_STRATEGY_SETTINGS');
                $stopReason = 'missing_strategy_settings';

                return;
            }

            $this->dailyPerformance->ensureTodayStat($bot);

            $sidewaysStop = $this->sidewaysGuard->blocksNewEntries($bot);

            $this->positions->monitor($bot, $sidewaysStop);

            if ($sidewaysStop) {
                $this->log->auditGuardEvent($bot, 'DAILY_TARGET_REACHED_SIDEWAYS', [
                    'profit_pct' => round($this->dailyPerformance->profitPct($bot), 2),
                ]);

                $this->log->botInfo('Bot cycle stopped by SidewaysMarketGuard', [
                    'bot_id' => $bot->id,
                ]);
                $stopReason = 'sideways_guard';

                return;
            }

            if ($this->hasReachedMaxPositions($bot)) {
                $this->log->botInfo('Bot cycle skipped scanning: max positions reached', [
                    'bot_id' => $bot->id,
                ]);
                $stopReason = 'max_positions';

                return;
            }

            $targets = $this->scanner->getTopVolatileSymbols($bot);

            if (empty($targets)) {
                $this->log->botWarning('Scanner returned no symbols', [
                    'bot_id' => $bot->id,
                ]);
                $stopReason = 'empty_scanner';

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
        } finally {
            $this->finishCycle($bot, $stats, $cycleStartedAt, $stopReason);
        }
    }

    /**
     * @param  array{scanned: int, rejected: int, executed: int, failed: int}  $stats
     */
    private function finishCycle(Bot $bot, array $stats, float $cycleStartedAt, string $stopReason): void
    {
        $bot->forceFill(['last_run_at' => now()])->saveQuietly();

        $this->log->botInfo('Bot cycle finished', [
            'bot_id' => $bot->id,
            'stop_reason' => $stopReason,
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

        $bot->strategy->loadMissing(['entrySettings', 'riskSettings']);

        $context = $this->contextFactory->make($bot, $symbol);
        $result = $this->pipeline->run($context);

        $this->logFinalStatus($result, $stats);
    }

    private function hasReachedMaxPositions(Bot $bot): bool
    {
        $maxPositions = (int) ($bot->strategy->riskSettings?->max_positions ?? 3);

        $openCount = $bot->positions()
            ->where('status', PositionStatus::Open)
            ->count();

        return $openCount >= $maxPositions;
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
                'blocked_by' => $context->blockedBy !== '' ? $context->blockedBy : null,
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