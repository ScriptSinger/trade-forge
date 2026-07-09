<?php

declare(strict_types=1);

namespace Database\Seeders\Bots;

use App\Enums\BotStatus;
use App\Models\Bot;
use App\Models\ExchangeAccount;
use App\Models\Strategy;
use App\Models\User;
use Database\Seeders\ExchangeAccounts\BybitMainExchangeAccountSeeder;
use Database\Seeders\Strategies\SpotBreakoutMode4StrategySeeder;
use Database\Seeders\Users\DemoUserSeeder;
use Illuminate\Database\Seeder;

class BybitSpotBotSeeder extends Seeder
{
    public const BOT_NAME = 'Bybit Spot Bot';

    public function run(): void
    {
        $user = User::query()
            ->where('email', DemoUserSeeder::EMAIL)
            ->firstOrFail();

        $exchangeAccount = ExchangeAccount::query()
            ->where('user_id', $user->id)
            ->where('name', BybitMainExchangeAccountSeeder::ACCOUNT_NAME)
            ->firstOrFail();

        $strategy = Strategy::query()
            ->where('name', SpotBreakoutMode4StrategySeeder::STRATEGY_NAME)
            ->firstOrFail();

        Bot::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'exchange_account_id' => $exchangeAccount->id,
                'strategy_id' => $strategy->id,
                'name' => self::BOT_NAME,
            ],
            [
                'status' => BotStatus::Paused->value,
                'last_run_at' => null,
            ],
        );
    }
}