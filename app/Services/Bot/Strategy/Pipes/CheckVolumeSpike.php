<?php

namespace App\Services\Bot\Strategy\Pipes;

use App\Services\Bot\Strategy\Pipes\Concerns\BlocksTradeContext;
use App\Services\Bot\Strategy\TradeContext;
use Closure;

class CheckVolumeSpike implements PipeContract
{
    use BlocksTradeContext;

    public function handle(TradeContext $context, Closure $next): mixed
    {
        $lastCandle = $context->lastCandle();
        $prevAvgVol = $context->indicators['prev_avg_volume'] ?? 0;
        $currentVol = $lastCandle['vol'] ?? 0;

        if ($currentVol <= $prevAvgVol) {
            return $this->block($context, "Low volume (Vol:{$currentVol} <= PrevAvg:{$prevAvgVol})");
        }

        return $next($context);
    }
}
