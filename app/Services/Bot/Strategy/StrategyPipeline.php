<?php

namespace App\Services\Bot\Strategy;

use Illuminate\Support\Facades\Pipeline;

class StrategyPipeline
{
    protected array $pipes = [
        Pipes\CheckKillSwitch::class,
        Pipes\CalculateIndicators::class,
        Pipes\CheckExistingPosition::class,
        Pipes\CheckGlobalLimits::class,
        Pipes\CheckBitcoinTrend::class,
        Pipes\ValidateTechnicalEntry::class,
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
