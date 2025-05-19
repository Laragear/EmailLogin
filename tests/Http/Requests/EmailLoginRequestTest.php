<?php

namespace Tests\Http\Requests;

use Illuminate\Foundation\Auth\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Laragear\EmailLogin\EmailLoginBroker;
use Laragear\EmailLogin\Http\Requests\EmailLoginRequest;
use Laragear\EmailLogin\Http\Requests\LoginByEmailRequest;
use Laragear\EmailLogin\Http\Routes;
use Laragear\EmailLogin\Mails\LoginEmail;
use Mockery\MockInterface;
use Tests\TestCase;

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
        $this->app->instance(
            'request', $request = EmailLoginRequest::create('https://localhost/auth/login/email', 'POST', $request)
        );

        $request->setContainer($this->app);
        $request->setRedirector($this->app->make('redirect'));
        $request->validateResolved();

        return $request;
    }

    public function test_uses_remember_with_default_key(): void
    {
        $request = $this->request(['email' => 'foo@bar.com', 'remember' => 'on']);

        $this->mock(EmailLoginBroker::class, function (MockInterface $mock) {
            $mock->expects('store')->andReturnSelf();
            $mock->expects('create')->withArgs(
                function (string $guard, mixed $id, mixed $expiration, bool $shouldRemember): bool {
                    return $shouldRemember === true;
                }
            )->andReturn('test-token');
        });

        static::assertTrue($request->send());
    }

    public function test_uses_validated_credentials(): void
    {
        $mail = Mail::fake();

        $request = $this->request();

        $request->validate(['email' => 'required|email']);

        static::assertTrue($request->send());

        $mail->assertQueued(LoginEmail::class);
    }

    public function test_send_and_back(): void
    {
        static::assertSame('https://localhost', $this->request()->sendAndBack()->getTargetUrl());
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
