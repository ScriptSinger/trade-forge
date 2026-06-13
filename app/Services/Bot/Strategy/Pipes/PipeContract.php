<?php

namespace App\Services\Bot\Strategy\Pipes;

use App\Services\Bot\Strategy\TradeContext;
use Closure;

interface PipeContract
{
    /**
     * Handle the trade context through the pipeline step.
     */
    public function handle(TradeContext $context, Closure $next): mixed;
}
