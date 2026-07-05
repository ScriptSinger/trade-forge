<?php

namespace App\Services\Bot\Strategy\Pipes;

use App\Services\Bot\Strategy\Pipes\Concerns\BlocksTradeContext;
use App\Services\Bot\Strategy\TradeContext;
use Closure;

class ValidateStrategySettings implements PipeContract
{
    use BlocksTradeContext;

    public function handle(TradeContext $context, Closure $next): mixed
    {
        $strategy = $context->bot->strategy;

        if (!$strategy?->entrySettings) {
            return $this->block($context, 'Missing strategy entry settings');
        }

        if (!$strategy->riskSettings) {
            return $this->block($context, 'Missing strategy risk settings');
        }

        if (empty($context->candles)) {
            return $this->block($context, 'No market candles available for ' . $context->symbol);
        }

        return $next($context);
    }
}