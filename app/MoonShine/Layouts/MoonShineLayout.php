<?php

declare(strict_types=1);

namespace App\MoonShine\Layouts;

use App\MoonShine\Resources\Trading\BotResource;
use App\MoonShine\Resources\Trading\BotRunResource;
use App\MoonShine\Resources\Trading\BotStatResource;
use App\MoonShine\Resources\Trading\ExchangeAccountResource;
use App\MoonShine\Resources\Trading\OrderResource;
use App\MoonShine\Resources\Trading\PositionResource;
use App\MoonShine\Resources\Trading\StrategyResource;
use App\MoonShine\Resources\Trading\TradeResource;
use App\MoonShine\Resources\Trading\UserResource;
use MoonShine\Laravel\Layouts\AppLayout;
use MoonShine\ColorManager\Palettes\RetroPalette;
use MoonShine\ColorManager\ColorManager;
use MoonShine\Contracts\ColorManager\ColorManagerContract;
use MoonShine\Contracts\ColorManager\PaletteContract;
use MoonShine\MenuManager\MenuGroup;
use MoonShine\MenuManager\MenuItem;
use MoonShine\Crud\Components\Layout\Notifications;
use MoonShine\UI\Components\Layout\Burger;
use MoonShine\UI\Components\Layout\Div;
use MoonShine\UI\Components\Layout\Menu;
use MoonShine\UI\Components\Layout\Sidebar;
use MoonShine\UI\Components\Layout\ThemeSwitcher;
use MoonShine\UI\Components\When;

final class MoonShineLayout extends AppLayout
{
    /**
     * @var null|class-string<PaletteContract>
     */
    protected ?string $palette = RetroPalette::class;

    protected function assets(): array
    {
        return [
            ...parent::assets(),
        ];
    }

    protected function menu(): array
    {
        return [
            ...parent::menu(),

            MenuGroup::make('Trading', [
                MenuItem::make(UserResource::class),
                MenuItem::make(ExchangeAccountResource::class),
                MenuItem::make(StrategyResource::class),
                MenuItem::make(BotResource::class),
                MenuItem::make(BotRunResource::class),
                MenuItem::make(OrderResource::class),
                MenuItem::make(PositionResource::class),
                MenuItem::make(TradeResource::class),
                MenuItem::make(BotStatResource::class),
            ], 'chart-bar'),
        ];
    }

    protected function getSidebarComponent(): Sidebar
    {
        return Sidebar::make([
            Div::make([
                $this->getLogoComponent()->minimized(),
            ])->class('menu-logo'),
            Div::make([
                When::make(
                    fn(): bool => $this->isUseNotifications(),
                    static fn(): array => [Notifications::make()],
                ),
                When::make(
                    fn(): bool => $this->hasThemes() && ! $this->isAlwaysDark(),
                    static fn(): array => [ThemeSwitcher::make()],
                ),
            ])->class('menu-actions'),
            Div::make(array_filter([
                $this->mobileMode ? null : Burger::make()->sidebar(),
            ]))->class('menu-burger'),

            Menu::make($this->menu())->class('menu menu--vertical')->name('sidebar-content'),
        ])->collapsed($this->secondBar === false);
    }

    /**
     * @param ColorManager $colorManager
     */
    protected function colors(ColorManagerContract $colorManager): void
    {
        parent::colors($colorManager);

        // $colorManager->primary('#00000');
    }
}
