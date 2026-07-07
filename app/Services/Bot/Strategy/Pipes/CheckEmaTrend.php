<?php

namespace App\Services\Bot\Strategy\Pipes;

use App\Services\Bot\Strategy\Pipes\Concerns\BlocksTradeContext;
use App\Services\Bot\Strategy\TradeContext;
use Closure;

class CheckEmaTrend implements PipeContract
{
    use BlocksTradeContext;

    public function handle(TradeContext $context, Closure $next): mixed
    {
        $emaFastArr = $context->indicators['ema_fast'] ?? [];
        $emaSlowArr = $context->indicators['ema_slow'] ?? [];
        $prevIndex = count($emaFastArr) - 2;

        if ($prevIndex < 0) {
            return $this->block($context, 'Insufficient EMA history');
        }

        $emaFast = $emaFastArr[$prevIndex] ?? 0;
        $emaSlow = $emaSlowArr[$prevIndex] ?? 0;

        if ($emaFast <= $emaSlow) {
            return $this->block($context, "Bearish EMA alignment on prev bar (Fast:{$emaFast} <= Slow:{$emaSlow})");
        }

        return $next($context);
    }
}
