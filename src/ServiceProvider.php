<?php

namespace MatinUtils\EasySocket;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->singleton('easy-socket', function ($app) {
            $archiver = new EasySocket;
            return $archiver;
        });

        $storageFolder = base_path('bootstrap/easySocket');
        if (!file_exists($storageFolder)) {
            mkdir($storageFolder, 0777);
        }
    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register()
    {
    }

    public function provides()
    {
        return [];
    }
}
