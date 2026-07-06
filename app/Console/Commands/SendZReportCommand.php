<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\BotStatus;
use App\Models\Bot;
use App\Services\Bot\ZReportService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendZReportCommand extends Command
{
    protected $signature = 'bot:z-report {--bot= : Bot ID} {--force : Send even if already sent or before scheduled time}';

    protected $description = 'Send daily Z-report to Telegram for completed trading day';

    public function handle(ZReportService $zReport): int
    {
        if (!$zReport->isEnabled()) {
            $this->warn('Z-report is disabled (Z_REPORT_ENABLED=false).');

            return self::SUCCESS;
        }

        $force = (bool) $this->option('force');
        $botId = $this->option('bot');

        $query = Bot::query()->where('status', BotStatus::Active);

        if ($botId) {
            $query->whereKey($botId);
        }

        $bots = $query->get();

        if ($bots->isEmpty()) {
            $this->info('No active bots found.');

            return self::SUCCESS;
        }

        $sent = 0;

        foreach ($bots as $bot) {
            if ($zReport->sendForBot($bot, force: $force)) {
                $sent++;
                $reportDate = $zReport->reportDate(Carbon::now()->timezone($zReport->timezone()));

                $this->info("Z-report sent for bot #{$bot->id} ({$bot->name}), date {$reportDate->toDateString()}.");
            }
        }

        if ($sent === 0) {
            $this->comment('No Z-reports were sent (not due, already sent, or delivery unavailable).');
        }

        return self::SUCCESS;
    }
}