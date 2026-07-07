<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Trading\Pages;

use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Laravel\Pages\Crud\FormPage;

final class TradingFormPage extends FormPage
{
    protected function fields(): iterable
    {
        return $this->getResource()->getFormFields();
    }

    protected function rules(DataWrapperContract $item): array
    {
        return method_exists($this->getResource(), 'formRules')
            ? $this->getResource()->formRules($item)
            : [];
    }
}
