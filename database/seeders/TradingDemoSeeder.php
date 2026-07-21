<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Bots\BybitSpotBotSeeder;
use Database\Seeders\ExchangeAccounts\BybitMainExchangeAccountSeeder;
use Database\Seeders\Strategies\StrategiesSeeder;
use Database\Seeders\Users\DemoUserSeeder;
use Illuminate\Database\Seeder;

/**
 * Orchestrates the demo trading graph.
 *
 * Strategies       → {family}-v{semver} presets (see StrategiesSeeder)
 * Users            → database/seeders/Users/
 * ExchangeAccounts → database/seeders/ExchangeAccounts/
 * Bots             → database/seeders/Bots/ (default: smart-hybrid-v1.0.0)
 */
class TradingDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            StrategiesSeeder::class,
            DemoUserSeeder::class,
            BybitMainExchangeAccountSeeder::class,
            BybitSpotBotSeeder::class,
        ]);
    }
}
