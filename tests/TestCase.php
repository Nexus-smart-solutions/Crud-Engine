<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Tests;

use Nexus\CrudEngine\Providers\CrudEngineServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            CrudEngineServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('filesystems.disks.testing', [
            'driver' => 'local',
            'root' => sys_get_temp_dir().'/crud-engine-tests',
        ]);
        $app['config']->set('filesystems.default', 'testing');
        $app['config']->set('crud-engine.files.disk', 'testing');
    }
}
