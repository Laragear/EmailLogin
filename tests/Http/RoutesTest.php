<?php

namespace Tests\Http;

use Illuminate\Http\Request;
use Illuminate\Routing\RouteRegistrar;
use Laragear\EmailLogin\Http\Routes;
use Tests\TestCase;
use function tap;

class RoutesTest extends TestCase
{
    public function test_register_routes(): void
    {
        static::assertInstanceOf(RouteRegistrar::class, Routes::register());

        /** @var \Illuminate\Routing\RouteCollection $routes */
        $routes = tap($this->app->make('router')->getRoutes())->refreshNameLookups();

        static::assertTrue($routes->hasNamedRoute('auth.email.send'));
        static::assertTrue($routes->hasNamedRoute('auth.email.login'));
        static::assertNotNull($routes->getByAction('App\Http\Controllers\Auth\EmailLoginController@login'));

        static::assertSame('auth/email/login', $routes->match(Request::create('/auth/email/login'))->uri());
        static::assertSame('auth/email/login', $routes->match(Request::create('/auth/email/login', 'POST'))->uri());

        static::assertSame(['guest'], $routes->match(Request::create('/auth/email/login'))->middleware());
        static::assertSame(['guest'], $routes->match(Request::create('/auth/email/login', 'POST'))->middleware());
    }

    public function test_uses_custom_routes(): void
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

    public function test_uses_custom_controller(): void
    {
        Routes::register(controller: 'Foo\Bar');

        /** @var \Illuminate\Routing\RouteCollection $routes */
        $routes = tap($this->app->make('router')->getRoutes())->refreshNameLookups();

        static::assertNotNull($routes->getByAction('Foo\Bar@send'));
        static::assertNotNull($routes->getByAction('Foo\Bar@show'));
        static::assertNotNull($routes->getByAction('Foo\Bar@login'));
    }

    public function test_uses_custom_middleware(): void
    {
        Routes::register(middleware: 'guest:web');

        /** @var \Illuminate\Routing\RouteCollection $routes */
        $routes = tap($this->app->make('router')->getRoutes())->refreshNameLookups();

        static::assertSame(['guest:web'], $routes->match(Request::create('/auth/email/send', 'POST'))->middleware());
        static::assertSame(['guest:web'], $routes->match(Request::create('/auth/email/login'))->middleware());
        static::assertSame(['guest:web'], $routes->match(Request::create('/auth/email/login', 'POST'))->middleware());
    }
}
