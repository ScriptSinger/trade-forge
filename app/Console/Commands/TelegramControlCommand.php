<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Notifications\TelegramNotifier;
use App\Services\Telegram\TelegramControlPanelService;
use Illuminate\Console\Command;

class TelegramControlCommand extends Command
{
    protected $signature = 'telegram:control
                            {--once : Handle a single polling batch and exit}';

    protected $description = 'Run Telegram control panel (long polling, like sample bot_manager.py)';

    public function handle(TelegramControlPanelService $panel, TelegramNotifier $telegram): int
    {
        if (!$panel->isEnabled()) {
            $this->error('Telegram control panel is disabled or TELEGRAM_BOT_TOKEN / TELEGRAM_CHAT_ID are missing.');

            return self::FAILURE;
        }

        $offset = 0;
        $pollTimeout = (int) config('trading.telegram.control_poll_timeout', 25);

        $this->info('Telegram control panel started. Waiting for commands...');

        do {
            $updates = $telegram->getUpdates($offset, $this->option('once') ? 0 : $pollTimeout);

            foreach ($updates as $update) {
                if (!is_array($update)) {
                    continue;
                }

                $updateId = (int) ($update['update_id'] ?? 0);

                if ($updateId > 0) {
                    $offset = $updateId + 1;
                }

                $panel->handleUpdate($update);
            }
        } while (!$this->option('once'));

        return self::SUCCESS;
    }
}