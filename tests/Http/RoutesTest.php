<?php

namespace Tests\Http;

use App\Http\Controllers\Auth\EmailLogin\EmailLoginController;
use Illuminate\Http\Request;
use Laragear\EmailLogin\Http\Routes;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use function tap;

class RoutesTest extends TestCase
{
    #[Test]
    public function register_routes(): void
    {
        $route = Routes::register();

        static::assertSame('auth.email.send', $route->getName());

        /** @var \Illuminate\Routing\RouteCollection $routes */
        $routes = tap($this->app->make('router')->getRoutes())->refreshNameLookups();

        static::assertTrue($routes->hasNamedRoute('auth.email.send'));
        static::assertTrue($routes->hasNamedRoute('auth.email.login'));
        static::assertNotNull($routes->getByAction('App\Http\Controllers\Auth\EmailLoginController@login'));

        static::assertSame('auth/email/login', $routes->match(Request::create('/auth/email/login'))->uri());
        static::assertSame('auth/email/login', $routes->match(Request::create('/auth/email/login', 'POST'))->uri());

        static::assertSame(
            ['guest', 'web', 'signed'], $routes->match(Request::create('/auth/email/login'))->middleware()
        );
        static::assertSame(
            ['guest', 'web', 'signed'], $routes->match(Request::create('/auth/email/login', 'POST'))->middleware()
        );
    }

    #[Test]
    public function uses_custom_routes(): void
    {
        Routes::register(
            '/foo/send',
            '/bar/login',
        );

        /** @var \Illuminate\Routing\RouteCollection $routes */
        $routes = tap($this->app->make('router')->getRoutes())->refreshNameLookups();

        static::assertTrue($routes->hasNamedRoute('auth.email.send'));
        static::assertTrue($routes->hasNamedRoute('auth.email.login'));
        static::assertNotNull($routes->getByAction('App\Http\Controllers\Auth\EmailLoginController@login'));

        static::assertSame('foo/send', $routes->match(Request::create('/foo/send', 'POST'))->uri());
        static::assertSame('bar/login', $routes->match(Request::create('/bar/login'))->uri());
        static::assertSame('bar/login', $routes->match(Request::create('/bar/login', 'POST'))->uri());
    }

    #[Test]
    public function uses_custom_controller(): void
    {
        Routes::register(controller: 'Foo\Bar');

        /** @var \Illuminate\Routing\RouteCollection $routes */
        $routes = tap($this->app->make('router')->getRoutes())->refreshNameLookups();

        static::assertNotNull($routes->getByAction('Foo\Bar@send'));
        static::assertNotNull($routes->getByAction('Foo\Bar@show'));
        static::assertNotNull($routes->getByAction('Foo\Bar@login'));
    }
}
