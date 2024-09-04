<?php

namespace Laragear\EmailLogin\Http;

use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use function app;

class Routes
{
    public const ROUTE_SEND = 'auth/email/send';
    public const ROUTE_SHOW = 'auth/email/show';
    public const ROUTE_LOGIN = 'auth/email/login';
    public const ROUTE_CONTROLLER = 'App\Http\Controllers\Auth\EmailLoginController';
    public const ROUTE_GUEST = '';
    /**
     * Register the default email login controller actions.
     */
    public static function register(
        string $send = self::ROUTE_SEND,
        string $login = self::ROUTE_LOGIN,
        string $controller = self::ROUTE_CONTROLLER,
        string $guest = self::ROUTE_GUEST,
    ): LaravelRoute {
        $route = null;

        Route::middleware("guest:$guest")
            ->controller($controller)
            ->group(static function () use ($send, $login, &$route): void {
                $route = Route::post($send, 'send')->name('auth.email.send');

                Route::get($login, 'show')->middleware('token.validate')->name('auth.email.login');
                Route::post($login, 'login')->middleware('token.consume');
            });

        return $route;
    }
}
