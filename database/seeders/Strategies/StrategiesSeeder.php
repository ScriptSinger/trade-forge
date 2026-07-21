<?php

declare(strict_types=1);

namespace Database\Seeders\Strategies;

use Illuminate\Database\Seeder;

/**
 * Seeds every strategy preset.
 *
 * Naming convention (best practice):
 *   {family}-v{MAJOR.MINOR.PATCH}
 *
 * Examples:
 *   smart-hybrid-v1.0.0
 *   andrew-pro-v6.3.0
 *   smart-hybrid-v1.1.0   ← next experiment: copy seeder, bump VERSION
 *
 * Rules:
 * - FAMILY = stable product id only (mode/style). No spot/TF/period — those are entry fields
 * - VERSION = semver; only this changes for a new published preset
 * - STRATEGY_NAME = FAMILY-vVERSION (unique row in strategies.name)
 * - Do not rename in place if bots already point at an old version; add a new seeder/version
 */
class StrategiesSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SpotBreakoutMode4StrategySeeder::class,
            AndrewProV63StrategySeeder::class,
        ]);
    }
}
