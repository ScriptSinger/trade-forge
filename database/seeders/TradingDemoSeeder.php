<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Bots\BybitSpotBotSeeder;
use Database\Seeders\ExchangeAccounts\BybitMainExchangeAccountSeeder;
use Database\Seeders\Strategies\SpotBreakoutMode4StrategySeeder;
use Database\Seeders\Users\DemoUserSeeder;
use Illuminate\Database\Seeder;

/**
 * Orchestrates the demo trading graph.
 *
 * Users            → database/seeders/Users/
 * ExchangeAccounts → database/seeders/ExchangeAccounts/
 * Bots             → database/seeders/Bots/
 * Strategies       → database/seeders/Strategies/
 */
class TradingDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SpotBreakoutMode4StrategySeeder::class,
            DemoUserSeeder::class,
            BybitMainExchangeAccountSeeder::class,
            BybitSpotBotSeeder::class,
        ]);
    }
}