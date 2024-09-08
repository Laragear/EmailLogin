<?php

namespace Tests\Http\Requests;

use Illuminate\Contracts\Auth\Factory;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Session\Session;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Response;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Validation\ValidationException;
use Laragear\EmailLogin\EmailLoginBroker;
use Laragear\EmailLogin\EmailLoginIntent;
use Laragear\EmailLogin\Http\Requests\LoginByEmailRequest;
use Laragear\EmailLogin\Http\Routes;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class LoginByEmailRequestTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        $this->loadLaravelMigrations();

        User::forceCreate([
            'name' => 'foo',
            'email' => 'foo@bar.com',
            'password' => 'test_password'
        ]);
    }

    protected function defineRoutes($router): void
    {
        Routes::register();
    }

    protected function request(string $method, array $params = null): LoginByEmailRequest
    {
        $params ??= [
            'token' => static::TOKEN,
            'store' => 'array'
        ];

        $request = LoginByEmailRequest::create('https://localhost/auth/login/email', $method, $params);
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make('redirect'));
        $this->app->make(StartSession::class)->handle($request, fn() => new Response());
        $request->validateResolved();

        $this->app->instance('request', $request);

        return $request;
    }

    public function test_expired_exception_without_token_key_on_get(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Page Expired');

        try {
            $this->request('get', ['store' => 'array']);
        } catch (HttpException $exception) {
            static::assertSame(419, $exception->getStatusCode());

            throw $exception;
        }
    }

    public function test_expired_exception_without_store_key_on_get(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Page Expired');

        try {
            $this->request('get', ['token' => static::TOKEN]);
        } catch (HttpException $exception) {
            static::assertSame(419, $exception->getStatusCode());

            throw $exception;
        }
    }

    public function test_expired_exception_with_store_invalid(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Page Expired');

        try {
            $this->request('get', ['token' => static::TOKEN, 'store' => 'invalid']);
        } catch (HttpException $exception) {
            static::assertSame(419, $exception->getStatusCode());

            throw $exception;
        }
    }

    public function test_expired_exception_on_token_not_found(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Page Expired');

        try {
            $this->request('get');
        } catch (HttpException $exception) {
            static::assertSame(419, $exception->getStatusCode());

            throw $exception;
        }
    }

    public function test_validation_exception_without_store_key_on_post(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The token field is required');

        $this->request('post', ['store' => 'array']);
    }

    public function test_validation_exception_with_invalid_store(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The token is invalid or has expired');

        $this->request('post', ['token' => static::TOKEN, 'store' => 'invalid']);
    }

    public function test_validation_exception_without_token_key_on_post(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The store field is required');

        $this->request('post', ['token' => static::TOKEN]);
    }

    public function test_doesnt_login_the_user_on_get(): void
    {
        $broker = $this->mock(EmailLoginBroker::class);
        $broker->expects('store')->with('array')->andReturnSelf();
        $broker->expects('get')->with(static::TOKEN)->andReturn(new EmailLoginIntent('web', 'test-id', true, '/', []));

        $this->request('get');

        $this->assertGuest();
    }

    public function test_logs_in_the_user_on_post(): void
    {
        $broker = $this->mock(EmailLoginBroker::class);
        $broker->expects('store')->with('array')->andReturnSelf();
        $broker->expects('pull')->with(static::TOKEN)->andReturn(
            new EmailLoginIntent('test-guard', 'test-id', true, '/test', [])
        );

        $guard = $this->mock(StatefulGuard::class);
        $guard->expects('loginUsingId')->with('test-id', true)->andReturnTrue();

        $auth = $this->mock(Factory::class);
        $auth->expects('guard')->with('test-guard')->andReturn($guard);
        $this->instance('auth', $auth);

        $this->request('post');

        static::assertSame('/test', $this->app->make('session')->get('url.intended'));
    }

    public function test_returns_metadata(): void
    {
        $broker = $this->mock(EmailLoginBroker::class);
        $broker->expects('store')->with('array')->andReturnSelf();
        $broker->expects('pull')->with(static::TOKEN)->andReturn(
            new EmailLoginIntent('web', 1, true, null, ['foo' => ['bar' => 'baz']])
        );

        $request = $this->request('post');

        static::assertSame('baz', $request->metadata('foo.bar'));
    }

    public function test_returns_to_intended(): void
    {
        $broker = $this->mock(EmailLoginBroker::class);
        $broker->expects('store')->with('array')->andReturnSelf();
        $broker->expects('pull')->with(static::TOKEN)->andReturn(
            new EmailLoginIntent('web', 1, true, '/test', [])
        );

        $request = $this->request('post');

        static::assertSame('https://localhost/test', $request->toIntended()->getTargetUrl());
    }
}
