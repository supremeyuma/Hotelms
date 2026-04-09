<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $configCachePath = Application::inferBasePath().'/bootstrap/cache/config.php';

        if (file_exists($configCachePath)) {
            throw new RuntimeException(
                'Refusing to run tests with cached config. Run "php artisan config:clear" first so tests cannot hit MySQL.'
            );
        }

        $app = require Application::inferBasePath().'/bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        if ($app['config']->get('database.default') !== 'sqlite') {
            throw new RuntimeException(
                'Refusing to run tests without sqlite as the default database connection.'
            );
        }

        return $app;
    }
}
