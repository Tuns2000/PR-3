<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\IssService;
use App\Services\OsdrService;
use App\Services\JwstService;
use App\Services\AstronomyService;
use App\Repositories\IssRepository;
use App\Repositories\OsdrRepository;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Регистрация Repositories как синглтонов
        $this->app->singleton(IssRepository::class, function ($app) {
            return new IssRepository();
        });

        $this->app->singleton(OsdrRepository::class, function ($app) {
            return new OsdrRepository();
        });

        // Регистрация Services с внедрением зависимостей
        $this->app->singleton(IssService::class, function ($app) {
            return new IssService($app->make(IssRepository::class));
        });

        $this->app->singleton(OsdrService::class, function ($app) {
            return new OsdrService($app->make(OsdrRepository::class));
        });

        $this->app->singleton(JwstService::class, function ($app) {
            return new JwstService();
        });

        $this->app->singleton(AstronomyService::class, function ($app) {
            return new AstronomyService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}