<?php

namespace App\Console\Commands;

use App\Enums\BotStatus;
use App\Models\Bot;
use App\Services\Bot\BotEngine;
use Illuminate\Console\Command;
use MoonShine\Laravel\Notifications\MoonShineNotification;
use MoonShine\Support\Enums\Color;

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
    public function handle(BotEngine $engine): void
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

                MoonShineNotification::send(
                    message: "Бот {$bot->name} завершил анализ рынка",
                    color: Color::SUCCESS
                );
            } catch (\Exception $e) {
                $this->error("Error running bot {$bot->name}: {$e->getMessage()}");
            }
        }
    }
}
