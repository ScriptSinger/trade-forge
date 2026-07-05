<?php

namespace App\Services\Bot\Strategy;

use Illuminate\Support\Facades\Pipeline;

class StrategyPipeline
{
    /**
     * The array of class pipes to through.
     * All pipes must implement PipeContract.
     */
    protected array $pipes = [
        Pipes\CalculateIndicators::class,
        Pipes\CheckExistingPosition::class,
        Pipes\CheckGlobalLimits::class,
        Pipes\CheckBtcTrend::class,

        // Modular Entry Conditions
        Pipes\CheckAdxStrength::class,
        Pipes\CheckEmaTrend::class,
        Pipes\CheckBreakoutLevel::class,
        Pipes\CheckVolumeSpike::class,

        Pipes\DetermineStrategyMode::class,
        Pipes\ApplyRiskManagement::class,
        Pipes\ExecuteTrade::class,
    ];

    /**
     * Run the trade context through the pipeline.
     */
    public function run(TradeContext $context): TradeContext
    {
        return Pipeline::send($context)
            ->through($this->pipes)
            ->thenReturn();
    }
}
