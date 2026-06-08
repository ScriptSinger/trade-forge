<?php

namespace App\Providers;

use App\MoonShine\Notifications\ReverbNotificationSystem;
use Illuminate\Support\ServiceProvider;
use MoonShine\Crud\Contracts\Notifications\MoonShineNotificationContract;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            MoonShineNotificationContract::class,
            ReverbNotificationSystem::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
