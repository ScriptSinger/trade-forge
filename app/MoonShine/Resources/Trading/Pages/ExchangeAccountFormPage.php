<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Trading\Pages;

use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\ActionButton;

final class ExchangeAccountFormPage extends FormPage
{
    protected function buttons(): ListOf
    {
        $buttons = parent::buttons();

        if (! $this->getResource()->getItemID()) {
            return $buttons;
        }

        $buttons->add(
            ActionButton::make(
                'Check connection',
                url: $this->getResource()->getRoute(
                    'handler',
                    $this->getResource()->getItemID(),
                    ['handlerUri' => 'check-connection'],
                ),
            )
                ->icon('signal')
                ->withoutLoading()
        );

        return $buttons;
    }
}
