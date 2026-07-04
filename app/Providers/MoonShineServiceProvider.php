<?php

declare(strict_types=1);

namespace App\Providers;

use App\MoonShine\Resources\Trading\BotResource;
use App\MoonShine\Resources\Trading\BotRunResource;
use App\MoonShine\Resources\Trading\BotStatResource;
use App\MoonShine\Resources\Trading\ExchangeAccountResource;
use App\MoonShine\Resources\Trading\OrderResource;
use App\MoonShine\Resources\Trading\PositionResource;
use App\MoonShine\Resources\Trading\StrategyResource;
use App\MoonShine\Resources\Trading\TradeResource;
use App\MoonShine\Resources\Trading\UserResource;
use Illuminate\Support\ServiceProvider;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Laravel\DependencyInjection\MoonShine;
use MoonShine\Laravel\DependencyInjection\MoonShineConfigurator;
use App\MoonShine\Resources\MoonShineUser\MoonShineUserResource;
use App\MoonShine\Resources\MoonShineUserRole\MoonShineUserRoleResource;
use App\MoonShine\Resources\Trading\StrategyBtcTrendFilterResource;
use App\MoonShine\Resources\Trading\StrategyEntrySettingsResource;
use App\MoonShine\Resources\Trading\StrategyRiskSettingsResource;

class MoonShineServiceProvider extends ServiceProvider
{
    /**
     * @param  CoreContract<MoonShineConfigurator>  $core
     */
    public function boot(CoreContract $core): void
    {
        $core
            ->resources([
                UserResource::class,
                ExchangeAccountResource::class,
                StrategyResource::class,
                BotResource::class,
                BotRunResource::class,
                OrderResource::class,
                PositionResource::class,
                TradeResource::class,
                BotStatResource::class,
                MoonShineUserResource::class,
                MoonShineUserRoleResource::class,
                StrategyEntrySettingsResource::class,
                StrategyRiskSettingsResource::class,
                StrategyBtcTrendFilterResource::class
            ])
            ->pages([
                ...$core->getConfig()->getPages(),
            ])
        ;
    }
}
