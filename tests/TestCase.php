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

        $default = $app['config']->get('database.default');

        if ($default !== 'sqlite') {
            // Default behaviour is unchanged: without an explicit opt-in a test
            // run that is not sqlite-backed fails hard, so a stray MySQL (or
            // mis-set) connection is never reached by the suite.
            //
            // Phase 8's cloud engine is Postgres, so an operator who has staged
            // a scratch Postgres instance may opt in for the real-engine run
            // with HOTELMS_TEST_ALLOW_PGSQL=1. Only pgsql is permitted; the
            // hard failure above still blocks every other engine.
            $allowPgsql = filter_var(env('HOTELMS_TEST_ALLOW_PGSQL', false), FILTER_VALIDATE_BOOL);

            if (! ($allowPgsql && $default === 'pgsql')) {
                throw new RuntimeException(
                    'Refusing to run tests without sqlite as the default database connection.'
                );
            }
        }

        return $app;
    }
}
