<?php

namespace Tests\Console;

use Laragear\EmailLogin\EmailLoginServiceProvider;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

class EmailLoginInstallTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [EmailLoginServiceProvider::class];
    }

    protected function setUp(): void
    {
        $delete = function (): void {
            $files = $this->app->make('files');

            $files->delete($this->app->configPath('email-login.php'));
            $files->delete($this->app->path('Http/Controllers/Auth/EmailLoginController.php'));
            $files->deleteDirectory($this->app->resourcePath('views/vendor/email-login'));
        };

        $this->afterApplicationCreated($delete);
        $this->beforeApplicationDestroyed($delete);

        parent::setUp();
    }

    #[Test]
    public function calls_vendor_publish_for_all_assets(): void
    {
        $this->artisan('email-login:install')->run();

        static::assertFileExists($this->app->configPath('email-login.php'));
        static::assertFileExists($this->app->resourcePath('views/vendor/email-login/web/login.blade.php'));
        static::assertFileExists($this->app->resourcePath('views/vendor/email-login/mail/login.blade.php'));
        static::assertFileExists($this->app->path('Http/Controllers/Auth/EmailLoginController.php'));
    }
}
