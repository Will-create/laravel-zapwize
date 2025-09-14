<?php

namespace Zapwize\Laravel\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Zapwize\Laravel\ZapwizeServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            ZapwizeServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app)
    {
        return [
            'Zapwize' => 'Zapwize\Laravel\Facades\Zapwize',
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('zapwize.api_key', 'test-api-key');
    }
}
