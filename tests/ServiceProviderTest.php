<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Laragear\EmailLogin\EmailLoginBroker;
use Laragear\EmailLogin\EmailLoginServiceProvider;
use Laragear\MetaTesting\InteractsWithServiceProvider;
use PHPUnit\Framework\Attributes\Test;

class ServiceProviderTest extends TestCase
{
    use InteractsWithServiceProvider;

    public function test_merges_config(): void
    {
        $this->assertConfigMerged(EmailLoginServiceProvider::CONFIG);
    }

    public function test_registers_views(): void
    {
        $this->assertHasViews(EmailLoginServiceProvider::VIEWS, 'laragear');
    }

    public function test_bounds_email_login_broker(): void
    {
        static::assertThat(
            $this->app->isShared(EmailLoginBroker::class),
            static::isFalse(),
            "The 'Laragear\EmailLogin\EmailLoginBroker' is registered as a shared instance in the Service Container.",
        );
    }

    public function test_registers_command(): void
    {
        static::assertArrayHasKey('email-login:install', $this->app[Kernel::class]->all());
    }

    public function test_publishes_files(): void
    {
        $this->assertPublishes($this->app->configPath('email-login.php'), 'config');
        $this->assertPublishes($this->app->resourcePath('views/vendor/laragear'), 'views');
        $this->assertPublishes($this->app->path('Http/Controllers/Auth/EmailLoginController.php'), 'controllers');
    }
}
