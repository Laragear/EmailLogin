<?php

namespace Tests\Http\Requests;

use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Session\Session;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
use Illuminate\Session\SymfonySessionDecorator;
use Illuminate\Validation\ValidationException;
use Laragear\EmailLogin\EmailLoginBroker;
use Laragear\EmailLogin\Http\Requests\LoginByEmailRequest;
use Mockery;
use Mockery\MockInterface;
use Orchestra\Testbench\Attributes\WithMigration;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[WithMigration]
class LoginByEmailRequestTest extends TestCase
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

    #[Test]
    public function throws_if_request_missing_id(): void
    {
        $this->mock(EmailLoginBroker::class)->expects('missing')->never();

        $request = LoginByEmailRequest::create('https//localhost/login', 'GET', ['guard' => 'web']);

        $request->setContainer($this->app);
        $request->setRedirector($this->app->make('redirect'));

        $this->expectException(ValidationException::class);

        $request->validateResolved();
    }

    #[Test]
    public function throws_if_request_missing_guard(): void
    {
        $this->mock(EmailLoginBroker::class)->expects('missing')->never();

        $request = LoginByEmailRequest::create('https//localhost/login', 'GET', ['id' => 1]);

        $request->setContainer($this->app);
        $request->setRedirector($this->app->make('redirect'));

        $this->expectException(ValidationException::class);

        $request->validateResolved();
    }

    #[Test]
    public function throws_if_request_guard_not_on_app(): void
    {
        $this->mock(EmailLoginBroker::class)->expects('missing')->never();

        $request = LoginByEmailRequest::create('https//localhost/login', 'GET', ['id' => 1, 'guard' => 'invalid']);

        $request->setContainer($this->app);
        $request->setRedirector($this->app->make('redirect'));

        $this->expectException(ValidationException::class);

        $request->validateResolved();
    }

    #[Test]
    public function throws_if_request_remember_not_boolean(): void
    {
        $this->mock(EmailLoginBroker::class)->expects('missing')->never();

        $request = LoginByEmailRequest::create('https//localhost/login', 'GET', [
            'id' => 1, 'guard' => 'web', 'remember' => 'invalid'
        ]);

        $request->setContainer($this->app);
        $request->setRedirector($this->app->make('redirect'));

        $this->expectException(ValidationException::class);

        $request->validateResolved();
    }

    #[Test]
    public function throws_if_intent_not_found(): void
    {
        $this->mock(EmailLoginBroker::class)->expects('missing')->with('web', 1)->andReturnTrue();

        $request = LoginByEmailRequest::create('https//localhost/login', 'GET', ['id' => 1, 'guard' => 'web']);

        $request->setContainer($this->app);

        $this->expectException(ValidationException::class);

        $request->validateResolved();
    }

    #[Test]
    public function throws_if_user_not_found(): void
    {
        $guard = Mockery::mock(StatefulGuard::class);
        $guard->expects('loginUsingId')->with(1, false)->andReturnFalse();

        $this->mock('auth')->expects('guard')->andReturn($guard);

        $this->mock(EmailLoginBroker::class)->expects('missing')->with('web', 1)->andReturnFalse();

        $request = LoginByEmailRequest::create('https//localhost/login', 'GET', ['id' => 1, 'guard' => 'web']);

        $request->setContainer($this->app);

        $this->expectException(ValidationException::class);

        $request->validateResolved();
    }

    #[Test]
    public function logins_user(): void
    {
        $this->mock(EmailLoginBroker::class)->expects('missing')->with('web', 1)->andReturnFalse();

        $this->startSession();

        $request = LoginByEmailRequest::create('https//localhost/login', 'GET', ['id' => 1, 'guard' => 'web']);
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make('redirect'));
        $request->setSession(new class($this->app->make('session.store')) extends SymfonySessionDecorator {
            public function regenerate($value) {
                return $this->store->regenerate($value);
            }
        });

        $request->validateResolved();

        $this->assertAuthenticated();
    }

    #[Test]
    public function logins_user_with_remember(): void
    {
        $guard = Mockery::mock(StatefulGuard::class);
        $guard->expects('loginUsingId')->with(1, true)->andReturn(new User());

        $this->mock('auth')->expects('guard')->andReturn($guard);

        $this->mock(EmailLoginBroker::class)->expects('missing')->with('web', 1)->andReturnFalse();

        $this->startSession();

        $request = LoginByEmailRequest::create('https//localhost/login', 'GET', [
            'id' => 1, 'guard' => 'web', 'remember' => '1'
        ]);
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make('redirect'));
        $request->setSession(new class($this->app->make('session.store')) extends SymfonySessionDecorator {
            public function regenerate($value) {
                return $this->store->regenerate($value);
            }
        });

        $request->validateResolved();
    }

    #[Test]
    public function logins_user_with_intended(): void
    {
        $guard = Mockery::mock(StatefulGuard::class);
        $guard->expects('loginUsingId')->with(1, true)->andReturn(new User());

        $this->mock('auth')->expects('guard')->andReturn($guard);

        $this->mock(EmailLoginBroker::class)->expects('missing')->with('web', 1)->andReturnFalse();

        $this->startSession();

        $request = LoginByEmailRequest::create('https//localhost/login', 'GET', [
            'id' => 1, 'guard' => 'web', 'remember' => '1', 'intended' => 'foo/bar'
        ]);
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make('redirect'));
        $request->setSession(new class($this->app->make('session.store')) extends SymfonySessionDecorator {
            public function regenerate($value) {
                return $this->store->regenerate($value);
            }
            public function put($key, $value = null) {
                $this->store->put($key, $value);
            }
        });

        $request->validateResolved();

        static::assertSame('http://localhost/foo/bar', $this->app->make('redirect')->intended()->getTargetUrl());
    }

    #[Test]
    public function destroys_session_on_regeneration(): void
    {
        LoginByEmailRequest::$destroyOnRegeneration = true;

        $this->mock(EmailLoginBroker::class)->expects('missing')->with('web', 1)->andReturnFalse();

        $session = $this->mock(Session::class, static function (MockInterface $mock): void {
            $mock->expects('regenerate')->with(true);
        });

        $request = LoginByEmailRequest::create('https//localhost/login', 'GET', ['id' => 1, 'guard' => 'web']);
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make('redirect'));
        $request->setSession(new class($session) extends SymfonySessionDecorator {
            public function regenerate($value) {
                return $this->store->regenerate($value);
            }
        });

        $request->validateResolved();

        $this->assertAuthenticated();
    }

    #[Test]
    public function calls_redirector(): void
    {
        $request = LoginByEmailRequest::create('https//localhost/login', 'GET', [
            'id' => 1, 'guard' => 'web', 'remember' => '1'
        ]);
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make('redirect'));

        static::assertInstanceOf(Redirector::class, $request->redirect());
        static::assertInstanceOf(RedirectResponse::class, $request->redirect('/foo/bar'));
        static::assertSame('http://localhost/foo/bar', $request->redirect('/foo/bar')->getTargetUrl());
    }
}
