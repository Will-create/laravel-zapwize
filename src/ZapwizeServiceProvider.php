<?php

namespace Zapwize\Laravel;

use Illuminate\Support\ServiceProvider;
use Zapwize\Laravel\Services\ZapwizeClient;

class ZapwizeServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/zapwize.php' => config_path('zapwize.php'),
            ], 'config');
        }
    }

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/zapwize.php', 'zapwize'
        );

        $this->app->singleton(ZapwizeClient::class, function ($app) {
            return new ZapwizeClient($app['config']->get('zapwize'));
        });

        $this->app->alias(ZapwizeClient::class, 'zapwize');
    }
}
