<?php

namespace SegWeb\Providers;

use Illuminate\Support\ServiceProvider;
use SegWeb\Services\FileService;
use SegWeb\Services\UserService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(UserService::class, function () {
            return new UserService();
        });

        $this->app->singleton(FileService::class, function () {
            return new FileService();
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
