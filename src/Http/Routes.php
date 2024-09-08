<?php

namespace Laragear\EmailLogin\Http;

use Illuminate\Routing\RouteRegistrar;
use Illuminate\Support\Facades\Route;

class Routes
{
    public const ROUTE_SEND = 'auth/email/send';
    public const ROUTE_LOGIN = 'auth/email/login';
    public const ROUTE_CONTROLLER = 'App\Http\Controllers\Auth\EmailLoginController';
    public const ROUTE_MIDDLEWARE = 'guest';

    /**
     * Register the default email login controller actions.
     */
    public static function register(
        string $send = self::ROUTE_SEND,
        string $login = self::ROUTE_LOGIN,
        string $controller = self::ROUTE_CONTROLLER,
        string|array $middleware = self::ROUTE_MIDDLEWARE,
    ): RouteRegistrar {
        $route = Route::controller($controller);

        if ($middleware) {
            $route->middleware($middleware);
        }

        return $route->group(static function () use ($send, $login): void {
            Route::post($send, 'send')->name('auth.email.send');

            Route::get($login, 'show')->name('auth.email.login');
            Route::post($login, 'login');
        });
    }
}
