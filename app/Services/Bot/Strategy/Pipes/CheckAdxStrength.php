<?php

namespace App\Services\Bot\Strategy\Pipes;

use App\Services\Bot\Strategy\Pipes\Concerns\BlocksTradeContext;
use App\Services\Bot\Strategy\TradeContext;
use Closure;

class CheckAdxStrength implements PipeContract
{
    use BlocksTradeContext;

    public function handle(TradeContext $context, Closure $next): mixed
    {
        $entrySettings = $context->bot->strategy->entrySettings;
        $adxArr = $context->indicators['adx'] ?? [];
        $adx = end($adxArr) ?: 0;
        $minAdx = (float) ($entrySettings->adx_min ?? 25);

        if ($adx < $minAdx) {
            return $this->block($context, "Low ADX ({$adx} < {$minAdx})");
        }

        return $next($context);
    }
}