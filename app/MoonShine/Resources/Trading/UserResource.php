<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Trading;

use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum as EnumRule;
use App\MoonShine\Resources\Trading\Pages\TradingFormPage;
use App\MoonShine\Resources\Trading\Pages\TradingIndexPage;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\Email;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Password;
use MoonShine\UI\Fields\PasswordRepeat;
use MoonShine\UI\Fields\Text;

#[Icon('users')]
#[Group('Trading', 'users')]
#[Order(0)]
final class UserResource extends TradingResource
{
    protected string $model = User::class;

    protected string $column = 'name';

    public function getTitle(): string
    {
        return 'Users';
    }

    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            Text::make('Name', 'name')->sortable(),
            Email::make('Email', 'email')->sortable(),
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
                Email::make('Email', 'email')->required(),
                Password::make('Password', 'password')->eye(),
                PasswordRepeat::make('Repeat password', 'password_confirmation')->eye(),
            ]),
        ];
    }

    protected function filters(): iterable
    {
        return [
            Text::make('Name', 'name'),
            Email::make('Email', 'email'),
        ];
    }

    public function formRules(DataWrapperContract $item): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique(User::class)->ignoreModel($item->getOriginal()),
            ],
            'password' => [
                ...$item->getKey() !== null ? ['sometimes', 'nullable'] : ['required'],
                'confirmed',
            ],
        ];
    }
}

