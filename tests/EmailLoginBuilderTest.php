<?php

namespace Tests;

use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Failed;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Cache\Factory as CacheFactoryContract;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Contracts\Mail\Factory as MailerFactoryContract;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Mail\Mailable;
use Illuminate\Routing\Route;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laragear\EmailLogin\EmailLoginBroker;
use Laragear\EmailLogin\EmailLoginBuilder;
use Laragear\EmailLogin\Http\Requests\LoginByEmailRequest;
use Laragear\EmailLogin\Http\Routes;
use Laragear\EmailLogin\Mails\LoginEmail;
use Mockery\MockInterface;
use RuntimeException;
use function method_exists;
use function now;
use function parse_str;
use function parse_url;
use const PHP_URL_QUERY;

class EmailLoginBuilderTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        LoginByEmailRequest::$destroyOnRegeneration = false;
        TestMailable::$run = false;
    }

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

    protected function builder(array $data = ['email' => 'foo@bar.com']): EmailLoginBuilder
    {
        $this->app->instance('request', Request::create('https://localhost/auth/login/email', 'POST', $data));

        return $this->app->make(EmailLoginBuilder::class);
    }

    public function test_no_credentials_returns_false(): void
    {
        static::assertFalse($this->builder()->send());
    }

    public function test_uses_config_expiration(): void
    {
        $this->freezeSecond();

        $this->app->make('config')->set('email-login.expiration', 30);

        $email = $this->app->instance(LoginEmail::class, new LoginEmail());

        static::assertTrue($this->builder()->withCredentials('email')->send());
        static::assertEquals(now()->addMinutes(30), $email->expiration);
    }

    public function test_uses_config_guard(): void
    {
        $this->app->make('config')->set('email-login.guard', 'test-guard');

        $guard = $this->app->make('auth')->guard();
        $auth = $this->mock(Factory::class);
        $auth->expects('guard')->with('test-guard')->andReturn($guard);

        $this->app->instance('auth', $auth);

        static::assertTrue($this->builder()->withCredentials('email')->send());
    }

    public function test_uses_config_route(): void
    {
        $this->app->make('config')->set('email-login.route.name', 'test-route');

        $url = $this->mock(UrlGenerator::class);
        $url->expects('route')->withArgs(fn (string $route): bool => 'test-route' === $route)->andReturn('test-route');
        $this->instance('url', $url);

        static::assertTrue($this->builder()->withCredentials('email')->send());
    }

    public function test_uses_config_queue_and_connection(): void
    {
        $config = $this->app->make('config');
        $config->set('queue.connections.test-connection', $config->get('queue.connections.sync'));

        $this->app->make('config')->set([
            'email-login.mail.connection' => 'test-connection',
            'email-login.mail.queue' => 'test-queue',
        ]);

        $mailable = $this->partialMock(LoginEmail::class, function (MockInterface $mock) {
            $mock->expects('onConnection')->with('test-connection')->andReturnSelf();
            $mock->expects('onQueue')->with('test-queue')->andReturnSelf();
        });

        $mailer = $this->mock(Mailer::class, function (MockInterface $mock) use ($mailable): void {
            $mock->expects('send')->with($mailable);
        });

        $mailerFactory = $this->mock(MailerFactoryContract::class);
        $mailerFactory->expects('mailer')->with(null)->andReturn($mailer);

        static::assertTrue($this->builder()->withCredentials('email')->withMailable($mailable)->send());
    }

    public function test_adds_credentials(): void
    {
        $builder = $this->builder(['email' => 'foo@bar.com', 'name' => 'bar', 'address' => 'bar']);

        $builder->withCredentials([
            'email',
            'name' => 'foo',
            static function ($query): void {
                static::assertInstanceOf(Builder::class, $query);

                $query->where('password', 'test_password');
            }
        ]);

        static::assertTrue($builder->send());
    }

    public function test_uses_custom_expiration(): void
    {
        $this->freezeSecond();

        $email = $this->app->instance(LoginEmail::class, new LoginEmail());

        static::assertTrue($this->builder()->withCredentials('email')->withExpiration(90)->send());
        static::assertEquals(now()->addMinutes(90), $email->expiration);
    }

    public function test_uses_expiration_as_string(): void
    {
        $this->freezeSecond();

        $email = $this->app->instance(LoginEmail::class, new LoginEmail());

        static::assertTrue($this->builder()->withCredentials('email')->withExpiration('30 minutes')->send());

        static::assertEquals($email->expiration, now()->addMinutes(30));
    }

    public function test_uses_with_remember_key_from_request(): void
    {
        $builder = $this->builder(['email' => 'foo@bar.com', 'something' => 'on']);

        $builder->withRemember('something');

        $this->mock(EmailLoginBroker::class, function (MockInterface $mock) {
            $mock->expects('store')->andReturnSelf();
            $mock->expects('create')->withArgs(
                function (string $guard, mixed $id, mixed $expiration, bool $shouldRemember): bool {
                    return $shouldRemember === true;
                }
            )->andReturn('test-token');
        });

        static::assertTrue($builder->withCredentials('email')->send());
    }

    public function test_uses_with_remember_condition(): void
    {
        $builder = $this->builder(['email' => 'foo@bar.com', 'remember' => '']);

        $builder->withRemember();

        $this->mock(EmailLoginBroker::class, function (MockInterface $mock) {
            $mock->expects('store')->andReturnSelf();
            $mock->expects('create')->withArgs(
                function (string $guard, mixed $id, mixed $expiration, bool $shouldRemember): bool {
                    return $shouldRemember === true;
                }
            )->andReturn('test-token');
        });

        static::assertTrue($builder->withCredentials('email')->send());
    }

    public function test_uses_with_remember_callback(): void
    {
        $builder = $this->builder(['email' => 'foo@bar.com', 'remember' => 'off']);

        $builder->withRemember(static function (mixed $request) {
            static::assertInstanceOf(Request::class, $request);

            return $request->remember === 'off';
        });

        $this->mock(EmailLoginBroker::class, function (MockInterface $mock) {
            $mock->expects('store')->andReturnSelf();
            $mock->expects('create')->withArgs(
                function (string $guard, mixed $id, mixed $expiration, bool $shouldRemember): bool {
                    return $shouldRemember === true;
                }
            )->andReturn('test-token');
        });

        static::assertTrue($builder->withCredentials('email')->send());
    }

    public function test_uses_custom_guard(): void
    {
        $guard = $this->app->make('auth')->guard();
        $auth = $this->mock(Factory::class);
        $auth->expects('guard')->with('test-guard')->andReturn($guard);

        $this->app->instance('auth', $auth);

        static::assertTrue($this->builder()->withCredentials('email')->withGuard('test-guard')->send());
    }

    public function test_uses_custom_guard_with_manually_instancing_user_provider(): void
    {
        $userProvider = $this->app->make('auth')->guard()->getProvider();

        $guard = $this->mock(Guard::class);

        $auth = $this->mock(Factory::class);
        $auth->expects('guard')->with('web')->andReturn($guard);
        $auth->expects('createUserProvider')->with('users')->andReturn($userProvider);

        $this->app->instance('auth', $auth);

        static::assertTrue($this->builder()->withCredentials('email')->withGuard('web')->send());
    }

    public function test_with_path(): void
    {
        $url = $this->mock(UrlGenerator::class);
        $url->expects('to')->withArgs(static function (string $path, array $parameters): bool {
            static::assertSame('foo', $path);
            static::assertSame('baz',$parameters['bar']);

            return true;
        })->andReturn('foobarbaz');
        $this->instance('url', $url);

        static::assertTrue($this->builder()->withCredentials('email')->withPath('foo', ['bar' => 'baz'])->send());
    }

    public function test_with_query(): void
    {
        if (method_exists(UrlGenerator::class, 'query')) {
            $this->markTestSkipped('The URL Generator Contract has a query method.');
        }

        $url = $this->mock(UrlGenerator::class, function (MockInterface $mock) {
            $mock->expects('to')->withArgs(static function (string $path): bool {
                $params = Str::of($path)
                    ->after('?')
                    ->explode('&')
                    ->mapWithKeys(fn($param) => [Str::before($param, '=') => Str::after($param, '=')]);

                static::assertSame('baz', $params['bar']);
                static::assertTrue(Str::isUlid($params['token']));
                static::assertSame('array', $params['store']);

                return true;
            })->andReturn('foobarbaz');
        });

        $this->instance('url', $url);

        static::assertTrue($this->builder()->withCredentials('email')->withQuery('foo', ['bar' => 'baz'])->send());
    }

    public function test_with_native_query(): void
    {
        if (!method_exists(UrlGenerator::class, 'query')) {
            $this->markTestSkipped("The URL Generator Contract doesn't have a [query] method.");
        }

        $url = $this->mock(UrlGenerator::class);
        $url->expects('query')->withArgs(static function (string $path, array $parameters): bool {
            static::assertSame('foo', $path);
            static::assertSame('baz',$parameters['bar']);

            return true;
        })->andReturn('foobarbaz');

        $this->instance('url', $url);

        static::assertTrue($this->builder()->withCredentials('email')->withQuery('foo', ['bar' => 'baz'])->send());
    }

    public function test_with_action(): void
    {
        $url = $this->mock(UrlGenerator::class);
        $url->expects('action')->withArgs(static function (string $path, array $parameters): bool {
            static::assertSame('foo', $path);
            static::assertSame('baz',$parameters['bar']);

            return true;
        })->andReturn('foobarbaz');

        $this->instance('url', $url);

        static::assertTrue($this->builder()->withCredentials('email')->withAction('foo', ['bar' => 'baz'])->send());
    }

    public function test_with_route(): void
    {
        $url = $this->mock(UrlGenerator::class);
        $url->expects('route')->withArgs(static function (string $path, array $parameters): bool {
            static::assertSame('foo', $path);
            static::assertSame('baz',$parameters['bar']);

            return true;
        })->andReturn('foobarbaz');

        $this->instance('url', $url);

        static::assertTrue($this->builder()->withCredentials('email')->withRoute('foo', ['bar' => 'baz'])->send());
    }

    public function test_with_parameters(): void
    {
        $url = $this->mock(UrlGenerator::class);
        $url->expects('route')->withArgs(static function (string $path, array $parameters): bool {
            static::assertSame('auth.email.login', $path);
            static::assertSame('baz',$parameters['bar']);

            return true;
        })->andReturn('foobarbaz');

        $this->instance('url', $url);

        static::assertTrue($this->builder()->withCredentials('email')->withParameters(['bar' => 'baz'])->send());
    }

    public function test_with_mailable_as_class_string(): void
    {
        $mail = Mail::fake();

        static::assertTrue($this->builder()->withCredentials('email')->withMailable(TestMailable::class)->send());

        static::assertTrue(TestMailable::$run);

        $mail->assertSent(TestMailable::class);
    }

    public function test_with_mailable_as_instance(): void
    {
        $mail = Mail::fake();

        static::assertTrue($this->builder()->withCredentials('email')->withMailable(new TestMailable())->send());

        $mail->assertSent(TestMailable::class);
    }

    public function test_with_mailable_as_callback(): void
    {
        $mail = Mail::fake();

        static::assertTrue($this->builder()->withCredentials('email')->withMailable(fn() => new TestMailable())->send());

        static::assertTrue(TestMailable::$run);

        $mail->assertSent(TestMailable::class);
    }

    public function test_throttles_fails_with_request_without_route_for_fingerprinting(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A key is required for this request to be throttled.');

        $this->builder()->withThrottle(30)->send();
    }

    public function test_throttles_with_request_fingerprint(): void
    {
        $builder = $this->builder();

        $this->app->make('request')->setRouteResolver(function () {
            return new Route('get', 'test-route', fn() => true);
        });

        static::assertTrue($builder->withCredentials('email')->withThrottle(30)->send());
    }

    public function test_throttles_by_with_custom_key(): void
    {
        $route = fn () => new Route('get', 'test-route', fn() => true);

        $mail = Mail::fake();

        $store = $this->app->make('cache')->store();

        $cache = $this->mock(CacheFactoryContract::class);
        $cache->expects('store')->with(null)->andReturn($store)->twice();
        $this->instance('cache', $cache);

        $this->mock(EmailLoginBroker::class, function (MockInterface $mock) {
            $mock->expects('store')->andReturnSelf();
            $mock->expects('create')->once()->andReturn('test-token');
        });

        $builder = $this->builder();

        $this->app->make('request')->setRouteResolver($route);

        static::assertFalse($builder->withCredentials('invalid')->withThrottle(30, key: 'test-key')->send());
        static::assertTrue($builder->withCredentials('email')->withThrottle(30, key: 'test-key')->send());
        static::assertTrue($store->has('email-login|throttle|test-key'));

        static::assertCount(1, $mail->queued(LoginEmail::class));
    }

    public function test_throttles_by_with_custom_store(): void
    {
        $route = fn () => new Route('get', 'test-route', fn() => true);

        $mail = Mail::fake();

        $store = $this->app->make('cache')->store();

        $cache = $this->mock(CacheFactoryContract::class);
        $cache->expects('store')->with('test-store')->andReturn($store)->twice();
        $this->instance('cache', $cache);

        $this->mock(EmailLoginBroker::class, function (MockInterface $mock) {
            $mock->expects('store')->andReturnSelf();
            $mock->expects('create')->once()->andReturn('test-token');
        });

        $builder = $this->builder();

        $this->app->make('request')->setRouteResolver($route);

        static::assertTrue($builder->withCredentials('email')->withThrottle(30, 'test-store', 'test')->send());
        static::assertTrue($builder->withCredentials('email')->withThrottle(30, 'test-store', 'test')->send());

        static::assertCount(1, $mail->queued(LoginEmail::class));
    }

    public function test_with_metadata(): void
    {
        EmailLoginBroker::$tokenGenerator = fn() => 'test-token';

        static::assertTrue($this->builder()->withCredentials('email')->withMetadata(['foo' => 'bar'])->send());

        $intent = $this->app->make(EmailLoginBroker::class)->get('test-token');

        static::assertSame(['foo' => 'bar'], $intent->metadata);
    }

    public function test_uses_custom_store_for_broker(): void
    {
        $this->mock(EmailLoginBroker::class, function (MockInterface $mock) {
            $mock->expects('store')->with('test-store')->andReturnSelf();
            $mock->expects('create')->once()->andReturn('test-token');
        });

        static::assertTrue($this->builder()->withCredentials('email')->withStore('test-store')->send());
    }

    public function test_creates_default_mailable(): void
    {
        $mail = Mail::fake();

        $this->builder()->withCredentials('email')->send();

        $mail->assertQueued(LoginEmail::class, function (LoginEmail $mailable): bool {
            parse_str(parse_url($mailable->url, PHP_URL_QUERY), $query);

            static::assertTrue(Str::isUlid($query['token']));
            static::assertSame('array', $query['store']);

            static::assertInstanceOf(User::class, $mailable->user);
            static::assertSame([['name' => 'foo', 'address' => 'foo@bar.com']], $mailable->to);
            static::assertSame('laragear::email-login.mail.login', $mailable->markdown);

            return true;
        });
    }

    public function test_returns_false_if_user_not_found(): void
    {
        static::assertFalse($this->builder(['email' => 'invalid@bar.com'])->withCredentials('email')->send());
    }

    public function test_fires_attempting_event(): void
    {
        $event = Event::fake([Attempting::class, Failed::class]);

        $this->builder()->withCredentials('email')->send();

        $event->assertDispatched(Attempting::class, function (Attempting $event): bool {
            static::assertSame('web', $event->guard);
            static::assertSame('foo@bar.com', $event->credentials['email']);
            static::assertFalse($event->remember);

            return true;
        });

        $event->assertNotDispatched(Failed::class);
    }

    public function test_fires_failed_event(): void
    {
        $event = Event::fake([Attempting::class, Failed::class]);

        $this->builder(['email' => 'invalid@bar.com'])->withCredentials('email')->send();

        $event->assertDispatched(Attempting::class);

        $event->assertDispatched(Failed::class, function (Failed $event): bool {
            static::assertSame('web', $event->guard);
            static::assertNull($event->user);
            static::assertSame('invalid@bar.com', $event->credentials['email']);

            return true;
        });
    }
}

class TestMailable extends Mailable
{
    public static bool $run = false;

    public function __construct()
    {
        static::$run = true;
    }
}
