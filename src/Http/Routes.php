<?php

namespace Laragear\EmailLogin\Http;

use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;

class Routes
{
    /**
     * Register the default email login controller actions.
     */
    public static function register(
        string $send = '/auth/email/send',
        string $login = '/auth/email/login',
        string $controller = 'App\Http\Controllers\Auth\EmailLoginController'
    ): LaravelRoute {
        $route = null;

        Route::middleware(['guest', 'web'])
            ->controller($controller)
            ->group(static function () use ($send, $login, &$route): void {
                $route = Route::post($send, 'send')->name('auth.email.send');
                Route::get($login, 'show')->name('auth.email.login')->middleware('signed');
                Route::post($login, 'login')->middleware('signed');
            });

        return $route;
    }
}
