<?php

namespace iProtek\Account;

use Illuminate\Support\ServiceProvider;

class AccountPackageServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Register package services
    }

    public function boot()
    {
        // Bootstrap package services
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        $this->mergeConfigFrom(
            __DIR__ . '/../config/iprotek.php', 'iprotek_account'
        );
    }
}