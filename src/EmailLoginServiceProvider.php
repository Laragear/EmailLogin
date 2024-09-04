<?php

namespace Laragear\EmailLogin;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use Laragear\TokenAction\Store;

/**
 * @internal
 */
class EmailLoginServiceProvider extends ServiceProvider
{
    public const CONFIG = __DIR__.'/../config/email-login.php';
    public const VIEWS = __DIR__.'/../resources/views';
    public const CONTROLLER = __DIR__.'/../stubs/controllers/EmailLoginController.php';

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(static::CONFIG, 'email-login');
        $this->loadViewsFrom(static::VIEWS, 'email-login');

        $this->app->bind(EmailLoginBroker::class, static function (Container $app): EmailLoginBroker {
            $config = $app->make('config');

            return new EmailLoginBroker(
                $app->make(Store::class),
                $config->get('email-login.cache.store'),
                $config->get('email-login.cache.prefix'),
            );
        });
    }

    /**
     * Bootstrap the package service.
     */
    public function boot(): void
    {
        $this->commands([Console\EmailLoginInstallCommand::class]);

        if ($this->app->runningInConsole()) {
            $this->publishes([static::CONFIG => $this->app->configPath('email-login.php')], 'config');
            $this->publishes([static::VIEWS => $this->app->resourcePath('views/vendor/email-login')], 'views');
            $this->publishes([
                // @phpstan-ignore-next-line
                static::CONTROLLER => $this->app->path('Http/Controllers/Auth/EmailLoginController.php')], 'controllers'
            );
        }
    }
}
