<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Strategies ({family}-v{semver}) via TradingDemoSeeder → StrategiesSeeder.
     */
    public function run(): void
    {
        $this->call([
            TradingDemoSeeder::class,
        ]);
    }
}
