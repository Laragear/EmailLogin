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

    #[Test]
    public function merges_config(): void
    {
        $this->assertConfigMerged(EmailLoginServiceProvider::CONFIG);
    }

    #[Test]
    public function registers_views(): void
    {
        $this->assertHasViews(EmailLoginServiceProvider::VIEWS, 'email-login');
    }

    #[Test]
    public function bounds_email_login_broker(): void
    {
        $this->assertHasSingletons(EmailLoginBroker::class);
    }

    #[Test]
    public function registers_command(): void
    {
        static::assertArrayHasKey('email-login:install', $this->app[Kernel::class]->all());
    }

    #[Test]
    public function publishes_files(): void
    {
        $this->assertPublishes($this->app->configPath('email-login.php'), 'config');
        $this->assertPublishes($this->app->resourcePath('views/vendor/email-login'), 'views');
        $this->assertPublishes($this->app->path('Http/Controllers/Auth/EmailLoginController.php'), 'controllers');
    }
}
