<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Trading\Pages;

use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\UI\Components\Table\TableBuilder;

final class TradingIndexPage extends IndexPage
{
    protected function fields(): iterable
    {
        return $this->getResource()->getIndexFields();
    }

    protected function filters(): iterable
    {
        return $this->getResource()->getFilters();
    }

    protected function modifyListComponent(ComponentContract $component): TableBuilder
    {
        return $component->columnSelection();
    }
}

