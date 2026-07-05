<?php

namespace App\Services\Bot\Strategy;

use Illuminate\Support\Facades\Pipeline;

class StrategyPipeline
{
    protected array $pipes = [
        Pipes\ValidateStrategySettings::class,
        Pipes\CheckExistingPosition::class,
        Pipes\CheckGlobalLimits::class,
        Pipes\CalculateIndicators::class,
        Pipes\CheckAdxStrength::class,
        Pipes\CheckEmaTrend::class,
        Pipes\CheckBreakoutLevel::class,
        Pipes\CheckVolumeSpike::class,
        Pipes\DetermineStrategyMode::class,
        Pipes\ApplyRiskManagement::class,
        Pipes\ExecuteTrade::class,
    ];

    public function run(TradeContext $context): TradeContext
    {
        return Pipeline::send($context)
            ->through($this->pipes)
            ->thenReturn();
    }
}