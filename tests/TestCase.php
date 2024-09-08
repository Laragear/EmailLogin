<?php

namespace Tests;

use Laragear\EmailLogin\EmailLoginServiceProvider;
use Laragear\TokenAction\TokenActionServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected const TOKEN = '01HK153X00KE6MK36Y8WV9PP85';

    protected function defineDatabaseMigrations(): void
    {
        $this->loadLaravelMigrations();
    }

    protected function getPackageProviders($app): array
    {
        return [EmailLoginServiceProvider::class, TokenActionServiceProvider::class];
    }
}
