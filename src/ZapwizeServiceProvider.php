<?php

namespace Zapwize\Laravel;

use Illuminate\Support\ServiceProvider;
use Zapwize\Laravel\Services\ZapwizeClient;

class ZapwizeServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole() && !$this->app->environment('testing')) {
            $this->publishes([
                __DIR__.'/../config/zapwize.php' => config_path('zapwize.php'),
            ], 'config');

            $this->app->booted(function () {
                if (config('zapwize.api_key')) {
                    $zapwize = $this->app->make(ZapwizeClient::class);
                    $zapwize->getLoop()->run();
                }
            });
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

        $this->commands([
            \Zapwize\Laravel\Console\Commands\TestConnection::class,
            \Zapwize\Laravel\Console\Commands\SendTestMessage::class,
            \Zapwize\Laravel\Console\Commands\ClearCache::class,
            \Zapwize\Laravel\Console\Commands\MessageStatus::class,
            \Zapwize\Laravel\Console\Commands\RetryFailedMessages::class,
        ]);
    }
}