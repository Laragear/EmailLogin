<?php

namespace Tests;

use Laragear\EmailLogin\EmailLoginServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function defineDatabaseMigrations(): void
    {
        $this->loadLaravelMigrations();
    }


    protected function getPackageProviders($app): array
    {
        return [EmailLoginServiceProvider::class];
    }
}
