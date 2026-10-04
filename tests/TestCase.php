<?php

namespace Thirdestonks\MemoryLane\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Thirdestonks\MemoryLane\MemoryLaneServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            MemoryLaneServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app)
    {
        // Record every request in tests; individual tests turn sampling down when they need to.
        $app['config']->set('memorylane.sample_rate', 1.0);

        // The dashboard sits behind the "web" group, which encrypts cookies.
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('m', 32)));

        // Local: in-memory SQLite ("testing"). CI sets DB_CONNECTION=mysql / sqlsrv to run the same suite there.
        $app['config']->set('database.default', env('DB_CONNECTION', 'testing'));
        $app['config']->set('database.connections.sqlsrv.trust_server_certificate', true);
    }
}
