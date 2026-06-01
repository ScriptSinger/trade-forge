<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Trading;

use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\Action;
use MoonShine\Support\ListOf;

abstract class TradingResource extends ModelResource
{
    protected bool $simplePaginate = true;

    protected function activeActions(): ListOf
    {
        return parent::activeActions()->except(Action::VIEW);
    }
}
