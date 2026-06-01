<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Trading;

use App\Enums\StrategyType;
use App\Models\Strategy;
use Illuminate\Validation\Rules\Enum as EnumRule;
use App\MoonShine\Resources\Trading\Pages\TradingFormPage;
use App\MoonShine\Resources\Trading\Pages\TradingIndexPage;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Json;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

#[Icon('adjustments-horizontal')]
#[Group('Trading', 'adjustments-horizontal')]
#[Order(2)]
final class StrategyResource extends TradingResource
{
    protected string $model = Strategy::class;

    protected string $column = 'name';

    protected array $with = ['bots'];

    public function getTitle(): string
    {
        return 'Strategies';
    }

    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            Text::make('Name', 'name')->sortable(),
            Enum::make('Type', 'type')->attach(StrategyType::class),
            Switcher::make('Active', 'is_active'),
            Date::make('Created at', 'created_at')
                ->format('d.m.Y H:i')
                ->sortable(),
        ];
    }

    protected function formFields(): iterable
    {
        return [
            Box::make([
                ID::make(),
                Text::make('Name', 'name')->required(),
                Enum::make('Type', 'type')
                    ->attach(StrategyType::class)
                    ->required(),
                Json::make('Settings', 'settings')
                    ->keyValue('Key', 'Value')
                    ->creatable(),
                Switcher::make('Active', 'is_active'),
            ]),
        ];
    }

    protected function filters(): iterable
    {
        return [
            Text::make('Name', 'name'),
            Enum::make('Type', 'type')->attach(StrategyType::class),
            Switcher::make('Active', 'is_active'),
        ];
    }

    public function formRules(DataWrapperContract $item): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', new EnumRule(StrategyType::class)],
            'settings' => ['required', 'array'],
            'is_active' => ['boolean'],
        ];
    }
}

