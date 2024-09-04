<?php

namespace Tests\Http\Requests;

use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Failed;
use Illuminate\Contracts\Auth\Factory as FactoryContract;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Foundation\Auth\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Laragear\EmailLogin\EmailLoginBroker;
use Laragear\EmailLogin\Http\Requests\EmailLoginRequest;
use Laragear\EmailLogin\Http\Requests\LoginByEmailRequest;
use Laragear\EmailLogin\Http\Routes;
use Laragear\EmailLogin\Mails\LoginEmail;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailLoginRequestTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        LoginByEmailRequest::$destroyOnRegeneration = false;
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

    protected function defineRoutes($router)
    {
        Routes::register();
    }

    protected function createRequest(array $request = ['email' => 'foo@bar.com']): EmailLoginRequest
    {
        $request = EmailLoginRequest::create('https://localhost/auth/login/email', 'POST', $request);
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make('redirect'));
        $request->validateResolved();

        $this->app->instance('request', $request);

        return $request;
    }

    #[Test]
    public function sends_email(): void
    {
        $request = $this->createRequest();

        $event = Event::fake();
        $mail = Mail::fake();

        $this->mock('url')->expects('temporarySignedRoute')
            ->with('auth.email.login', 5 * 60, ['id' => 1, 'guard' => 'web', 'remember' => false])
            ->andReturn('http://localhost/path/to/login/email');

        $this->mock(EmailLoginBroker::class)->expects('register')->with('web', 1, 60 * 5);

        $request->sendAndBack();

        $mail->assertQueued(LoginEmail::class, static function (LoginEmail $mail): bool {
            static::assertSame('email-login::mail.login', $mail->view);
            static::assertSame('http://localhost/path/to/login/email', $mail->url);
            static::assertSame(1, $mail->user->getAuthIdentifier());

            return true;
        });

        $event->assertDispatched(Attempting::class, static function (Attempting $event): bool {
            static::assertSame('web', $event->guard);
            static::assertSame(['email' => 'foo@bar.com'], $event->credentials);
            static::assertFalse($event->remember);

            return true;
        });

        $event->assertNotDispatched(Failed::class);
    }

    #[Test]
    public function sends_email_with_different_email_key(): void
    {
        $this->app->make('config')->set('email-login.guards.web', 'email_address');

        $request = $this->createRequest(['email_address' => 'foo@bar.com']);

        $event = Event::fake();
        $mail = Mail::fake();

        $this->mock('auth', function (MockInterface $mock): void {
            $userProvider = $this->mock(UserProvider::class, static function (MockInterface $mock): void {
                $mock->expects('retrieveByCredentials')
                    ->with(['email_address' => 'foo@bar.com'])
                    ->andReturn(User::find(1));
            });

            $mock->expects('guard')->with('web')->andReturn((object) []);
            $mock->expects('createUserProvider')->with('users')->andReturn($userProvider);
        });

        $this->mock('url')->expects('temporarySignedRoute')
            ->with('auth.email.login', 5 * 60, ['id' => 1, 'guard' => 'web', 'remember' => false])
            ->andReturn('http://localhost/path/to/login/email');

        $request->sendAndBack();

        $event->assertNotDispatched(Failed::class);
        $mail->assertQueued(LoginEmail::class);
    }

    #[Test]
    public function throws_if_user_email_not_found(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The email field must be a valid email address.');

        $this->createRequest(['email' => 'invalid@bar.com'])->sendAndBack();
    }

    #[Test]
    public function uses_custom_mailable_class(): void
    {
        $mail = Mail::fake();

        $this->createRequest()->withMailable(TestMailable::class)->sendAndBack();

        $mail->assertSent(TestMailable::class);
    }

    #[Test]
    public function uses_custom_mailable_object(): void
    {
        $mail = Mail::fake();

        $this->createRequest()->withMailable(new TestMailable())->sendAndBack();

        $mail->assertSent(TestMailable::class);
    }

    #[Test]
    public function uses_custom_mailable_closure(): void
    {
        $mail = Mail::fake();

        $this->createRequest()->withMailable(fn () => new TestMailable())->sendAndBack();

        $mail->assertSent(TestMailable::class);
    }

    #[Test]
    public function uses_custom_query(): void
    {
        $request = $this->createRequest();

        $this->mock('url')->expects('temporarySignedRoute')
            ->with('auth.email.login', 5 * 60, ['foo' => 'bar', 'guard' => 'web', 'id' => 1, 'remember' => false])
            ->andReturn('http://localhost/path/to/login/email');

        $request->withParameters(['foo' => 'bar'])->sendAndBack();
    }

    #[Test]
    public function uses_custom_route(): void
    {
        $request = $this->createRequest();

        $this->mock('url')->expects('temporarySignedRoute')
            ->with('foo.bar', 5 * 60, ['guard' => 'web', 'id' => 1, 'remember' => false])
            ->andReturn('http://localhost/path/to/login/email');

        $request->withRoute('foo.bar')->sendAndBack();
    }

    #[Test]
    public function uses_custom_route_with_query(): void
    {
        $request = $this->createRequest();

        $this->mock('url')->expects('temporarySignedRoute')
            ->with('foo.bar', 5 * 60, ['foo' => 'bar', 'guard' => 'web', 'id' => 1, 'remember' => false])
            ->andReturn('http://localhost/path/to/login/email');

        $request->withRoute('foo.bar', ['foo' => 'bar'])->sendAndBack();
    }

    #[Test]
    public function uses_custom_guard(): void
    {
        $request = $this->createRequest();

        $this->instance('auth', $this->mock(FactoryContract::class, function (MockInterface $mock): void {
            $mock->expects('guard')->andReturn($this->app->make('auth')->guard('web'));
        }));

        $this->mock('url')->expects('temporarySignedRoute')
            ->with('auth.email.login', 5 * 60, ['guard' => 'baz', 'id' => 1, 'remember' => false])
            ->andReturn('http://localhost/path/to/login/email');

        $request->withGuard('baz')->sendAndBack();
    }

    #[Test]
    public function uses_custom_remember_key(): void
    {
        $request = $this->createRequest(['email' => 'foo@bar.com', 'remember_device' => true]);

        $this->mock('url')->expects('temporarySignedRoute')
            ->with('auth.email.login', 5 * 60, ['guard' => 'web', 'id' => 1, 'remember' => true])
            ->andReturn('http://localhost/path/to/login/email');

        $request->withRemember('remember_device')->sendAndBack();
    }

    #[Test]
    public function uses_custom_link_ttl(): void
    {
        $request = $this->createRequest();

        $this->mock('url')->expects('temporarySignedRoute')
            ->with('auth.email.login', 10 * 60, ['guard' => 'web', 'id' => 1, 'remember' => false])
            ->andReturn('http://localhost/path/to/login/email');

        $request->expiresAt(10)->sendAndBack();
    }

    #[Test]
    public function uses_additional_credentials(): void
    {
        $request = $this->createRequest();

        $event = Event::fake();

        $this->mock('url')->expects('temporarySignedRoute')
            ->with('auth.email.login', 5 * 60, ['guard' => 'web', 'id' => 1, 'remember' => false])
            ->andReturn('http://localhost/path/to/login/email');

        $request->withCredentials(['name' => 'foo'])->sendAndBack();

        $event->assertDispatched(Attempting::class, static function (Attempting $event): bool {
            static::assertSame('web', $event->guard);
            static::assertSame(['email' => 'foo@bar.com', 'name' => 'foo'], $event->credentials);
            return true;
        });
    }

    #[Test]
    public function integrates_intended_route(): void
    {
        $request = $this->createRequest();

        $this->app->make('session')->put('url.intended', '/foo/bar');

        $this->mock('url')->expects('temporarySignedRoute')
            ->with('auth.email.login', 5 * 60, ['guard' => 'web', 'id' => 1, 'remember' => false, 'intended' => '/foo/bar'])
            ->andReturn('http://localhost/path/to/login/email');

        $request->sendAndBack();
    }

}

class TestMailable extends Mailable
{

}
