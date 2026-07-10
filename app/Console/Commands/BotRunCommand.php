<?php

namespace App\Console\Commands;

use App\Enums\BotStatus;
use App\Models\Bot;
use App\Services\Bot\Engine\BotEngine;
use App\Services\Bot\Performance\ZReportService;
use Illuminate\Console\Command;

class BotRunCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bot:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run all active trading bots';

    /**
     * Execute the console command.
     */
    public function handle(BotEngine $engine, ZReportService $zReport): void
    {
        $bots = Bot::where('status', BotStatus::Active)->get();

        if ($bots->isEmpty()) {
            $this->info('No active bots found.');

            return;
        }

        foreach ($bots as $bot) {
            $this->info("Executing bot: {$bot->name}");

            try {
                $engine->run($bot);
                $this->info("Bot {$bot->name} finished successfully.");

                if ($zReport->sendForBot($bot)) {
                    $this->info("Z-report sent for bot {$bot->name}.");
                }
            } catch (\Exception $e) {
                $this->error("Error running bot {$bot->name}: {$e->getMessage()}");
            }
        }
    }
}
