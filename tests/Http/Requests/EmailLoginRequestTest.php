<?php

namespace Tests\Http\Requests;

use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Failed;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Cache\Factory as CacheFactoryContract;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Contracts\Mail\Factory as MailerFactoryContract;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Foundation\Auth\User;
use Illuminate\Mail\Mailable;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laragear\EmailLogin\EmailLoginBroker;
use Laragear\EmailLogin\Http\Requests\EmailLoginRequest;
use Laragear\EmailLogin\Http\Requests\LoginByEmailRequest;
use Laragear\EmailLogin\Http\Routes;
use Laragear\EmailLogin\Mails\LoginEmail;
use Tests\TestCase;
use function method_exists;
use function now;
use function parse_str;
use function parse_url;
use const PHP_URL_QUERY;

class EmailLoginRequestTest extends TestCase
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

    protected function request(array $request = ['email' => 'foo@bar.com']): EmailLoginRequest
    {
        $request = EmailLoginRequest::create('https://localhost/auth/login/email', 'POST', $request);
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make('redirect'));
        $request->validateResolved();

        $this->app->instance('request', $request);

        return $request;
    }

    public function test_uses_default_rules_to_validate(): void
    {
        $request = $this->request();

        static::assertTrue($request->send());

        static::assertSame(['email' => 'foo@bar.com'], $request->validated());
    }

    public function test_uses_config_expiration(): void
    {
        $this->freezeSecond();

        $this->app->make('config')->set('email-login.expiration', 30);

        $this->app->beforeResolving(LoginEmail::class, function (string $class, array $parameters): void {
            static::assertEquals($parameters['expiration'], now()->addMinutes(30));
        });

        static::assertTrue($this->request()->send());
    }

    public function test_uses_config_guard(): void
    {
        $this->app->make('config')->set('email-login.guard', 'test-guard');

        $guard = $this->app->make('auth')->guard();
        $auth = $this->mock(Factory::class);
        $auth->expects('guard')->with('test-guard')->andReturn($guard);

        $this->app->instance('auth', $auth);

        static::assertTrue($this->request()->send());
    }

    public function test_uses_config_route(): void
    {
        $this->app->make('config')->set('email-login.route.name', 'test-route');

        $request = $this->request();

        $url = $this->mock(UrlGenerator::class);
        $url->expects('route')->withArgs(fn (string $route): bool => 'test-route' === $route)->andReturn('test-route');
        $this->instance('url', $url);

        static::assertTrue($request->send());
    }

    public function test_uses_remember_with_default_key(): void
    {
        $request = $this->request(['email' => 'foo@bar.com', 'remember' => 'on']);

        $this->mock(EmailLoginBroker::class)->expects('create')->withArgs(
            function (string $guard, mixed $id, mixed $expiration, bool $shouldRemember): bool {
                return $shouldRemember === true;
            }
        )->andReturn('test-token');

        static::assertTrue($request->send());
    }

    public function test_uses_config_queue_and_connection(): void
    {
        $config = $this->app->make('config');
        $config->set('queue.connections.test-connection', $config->get('queue.connections.sync'));

        $this->app->make('config')->set([
            'email-login.mail.connection' => 'test-connection',
            'email-login.mail.queue' => 'test-queue',
        ]);

        $mailable = $this->mock(LoginEmail::class);
        $mailable->expects('onConnection')->with('test-connection')->andReturnSelf();
        $mailable->expects('onQueue')->with('test-queue')->andReturnSelf();

        $mailer = $this->mock(Mailer::class);
        $mailer->expects('send')->with($mailable);

        $mailerFactory = $this->mock(MailerFactoryContract::class);
        $mailerFactory->expects('mailer')->with(null)->andReturn($mailer);

        static::assertTrue($this->request()->withMailable($mailable)->send());
    }

    public function test_uses_validated_credentials(): void
    {
        $mail = Mail::fake();

        $request = $this->request();

        $request->validate(['email' => 'required|email']);

        static::assertTrue($request->send());

        $mail->assertQueued(LoginEmail::class);
    }

    public function test_adds_credentials(): void
    {
        $mail = Mail::fake();

        $request = $this->request(['email' => 'foo@bar.com', 'name' => 'bar', 'address' => 'bar']);

        $request->withCredentials([
            'email',
            'name' => 'foo',
            static function ($query): void {
                static::assertInstanceOf(Builder::class, $query);

                $query->where('password', 'test_password');
            }
        ]);

        static::assertTrue($request->send());

        $mail->assertQueued(LoginEmail::class);
    }

    public function test_uses_expiration(): void
    {
        $this->freezeSecond();

        $this->app->beforeResolving(LoginEmail::class, function (string $class, array $parameters): void {
            static::assertEquals($parameters['expiration'], now()->addMinutes(30));
        });

        static::assertTrue($this->request()->withExpiration(30)->send());
    }

    public function test_uses_expiration_as_string(): void
    {
        $this->freezeSecond();

        $this->app->beforeResolving(LoginEmail::class, function (string $class, array $parameters): void {
            static::assertEquals($parameters['expiration'], now()->addMinutes(30));
        });

        static::assertTrue($this->request()->withExpiration('30 minutes')->send());
    }

    public function test_uses_with_remember_key(): void
    {
        $request = $this->request(['email' => 'foo@bar.com', 'something' => 'on']);

        $request->withRemember('something');

        $this->mock(EmailLoginBroker::class)->expects('create')->withArgs(
            function (string $guard, mixed $id, mixed $expiration, bool $shouldRemember): bool {
                return $shouldRemember === true;
            }
        )->andReturn('test-token');

        static::assertTrue($request->send());
    }

    public function test_uses_with_remember_condition(): void
    {
        $request = $this->request(['email' => 'foo@bar.com', 'remember' => '']);

        $request->withRemember(true);

        $this->mock(EmailLoginBroker::class)->expects('create')->withArgs(
            function (string $guard, mixed $id, mixed $expiration, bool $shouldRemember): bool {
                return $shouldRemember === true;
            }
        )->andReturn('test-token');

        static::assertTrue($request->send());
    }

    public function test_uses_custom_guard(): void
    {
        $guard = $this->app->make('auth')->guard();
        $auth = $this->mock(Factory::class);
        $auth->expects('guard')->with('test-guard')->andReturn($guard);

        $this->app->instance('auth', $auth);

        static::assertTrue($this->request()->withGuard('test-guard')->send());
    }

    public function test_uses_custom_guard_with_manually_instancing_user_provider(): void
    {
        $userProvider = $this->app->make('auth')->guard()->getProvider();

        $guard = $this->mock(Guard::class);

        $auth = $this->mock(Factory::class);
        $auth->expects('guard')->with('web')->andReturn($guard);
        $auth->expects('createUserProvider')->with('users')->andReturn($userProvider);

        $this->app->instance('auth', $auth);

        static::assertTrue($this->request()->withGuard('web')->send());
    }

    public function test_with_path(): void
    {
        $request = $this->request();

        $url = $this->mock(UrlGenerator::class);
        $url->expects('to')->withArgs(static function (string $path, array $parameters): bool {
            static::assertSame('foo', $path);
            static::assertSame('baz',$parameters['bar']);

            return true;
        })->andReturn('foobarbaz');
        $this->instance('url', $url);

        static::assertTrue($request->withPath('foo', ['bar' => 'baz'])->send());
    }

    public function test_with_action(): void
    {
        $request = $this->request();

        $url = $this->mock(UrlGenerator::class);
        $url->expects('action')->withArgs(static function (string $path, array $parameters): bool {
            static::assertSame('foo', $path);
            static::assertSame('baz',$parameters['bar']);

            return true;
        })->andReturn('foobarbaz');
        $this->instance('url', $url);

        static::assertTrue($request->withAction('foo', ['bar' => 'baz'])->send());
    }

    public function test_with_route(): void
    {
        $request = $this->request();

        $url = $this->mock(UrlGenerator::class);
        $url->expects('route')->withArgs(static function (string $path, array $parameters): bool {
            static::assertSame('foo', $path);
            static::assertSame('baz',$parameters['bar']);

            return true;
        })->andReturn('foobarbaz');
        $this->instance('url', $url);

        static::assertTrue($request->withRoute('foo', ['bar' => 'baz'])->send());
    }

    public function test_with_parameters(): void
    {
        $request = $this->request();

        $url = $this->mock(UrlGenerator::class);
        $url->expects('route')->withArgs(static function (string $path, array $parameters): bool {
            static::assertSame('auth.email.login', $path);
            static::assertSame('baz',$parameters['bar']);

            return true;
        })->andReturn('foobarbaz');
        $this->instance('url', $url);

        static::assertTrue($request->withParameters(['bar' => 'baz'])->send());
    }

    public function test_with_mailable_as_class_string(): void
    {
        $mail = Mail::fake();

        static::assertTrue($this->request()->withMailable(TestMailable::class)->send());

        static::assertTrue(TestMailable::$run);

        $mail->assertSent(TestMailable::class);
    }

    public function test_with_mailable_as_instance(): void
    {
        $mail = Mail::fake();

        static::assertTrue($this->request()->withMailable(new TestMailable())->send());

        $mail->assertSent(TestMailable::class);
    }

    public function test_with_mailable_as_callback(): void
    {
        $mail = Mail::fake();

        static::assertTrue($this->request()->withMailable(fn() => new TestMailable())->send());

        static::assertTrue(TestMailable::$run);

        $mail->assertSent(TestMailable::class);
    }

    public function test_throttles_by(): void
    {
        $route = fn () => new Route('get', 'test-route', fn() => true);

        $mail = Mail::fake();

        static::assertTrue($this->request()->setRouteResolver($route)->withThrottle(30)->send());
        static::assertTrue($this->request()->setRouteResolver($route)->withThrottle(30)->send());

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

        $this->mock(EmailLoginBroker::class)->expects('create')->andReturn('test-token');

        static::assertTrue($this->request()->setRouteResolver($route)->withThrottle(30, 'test-store')->send());
        static::assertTrue($this->request()->setRouteResolver($route)->withThrottle(30, 'test-store')->send());

        static::assertCount(1, $mail->queued(LoginEmail::class));
    }

    public function test_throttles_by_with_custom_key(): void
    {
        $route = fn () => new Route('get', 'test-route', fn() => true);

        $mail = Mail::fake();

        $store = $this->app->make('cache')->store();

        $cache = $this->mock(CacheFactoryContract::class);
        $cache->expects('store')->with(null)->andReturn($store)->twice();
        $this->instance('cache', $cache);

        $this->mock(EmailLoginBroker::class)->expects('create')->andReturn('test-token');

        static::assertTrue($this->request()->setRouteResolver($route)->withThrottle(30, key: 'test-key')->send());
        static::assertTrue($this->request()->setRouteResolver($route)->withThrottle(30, key: 'test-key')->send());
        static::assertTrue($store->has('email-login|throttle|test-key'));

        static::assertCount(1, $mail->queued(LoginEmail::class));
    }

    public function test_with_metadata(): void
    {
        EmailLoginBroker::$tokenGenerator = fn() => 'test-token';

        static::assertTrue($this->request()->withMetadata(['foo' => 'bar'])->send());

        $intent = $this->app->make(EmailLoginBroker::class)->get('test-token');

        static::assertSame(['foo' => 'bar'], $intent->metadata);
    }

    public function test_send_and_back(): void
    {
        static::assertSame('https://localhost', $this->request()->sendAndBack()->getTargetUrl());
    }

    public function test_creates_default_mailable(): void
    {
        $mail = Mail::fake();

        $this->request()->send();

        $mail->assertQueued(LoginEmail::class, function (LoginEmail $mailable): bool {
            parse_str(parse_url($mailable->url, PHP_URL_QUERY), $query);

            static::assertTrue(Str::isUlid($query['token']));
            static::assertSame('web', $query['store']);

            static::assertInstanceOf(User::class, $mailable->user);
            static::assertSame([['name' => 'foo', 'address' => 'foo@bar.com']], $mailable->to);
            static::assertSame('laragear::email-login.mail.login', $mailable->markdown);

            return true;
        });
    }

    public function test_returns_false_if_user_not_found(): void
    {
        static::assertFalse($this->request(['email' => 'invalid@bar.com'])->send());
    }

    public function test_fires_attempting_event(): void
    {
        $event = Event::fake([Attempting::class, Failed::class]);

        $this->request()->send();

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

        $this->request(['email' => 'invalid@bar.com'])->send();

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
