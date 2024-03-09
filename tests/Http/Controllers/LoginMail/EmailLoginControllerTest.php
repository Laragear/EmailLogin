<?php

namespace Tests\Http\Controllers\LoginMail;

use Illuminate\Foundation\Auth\User;
use Laragear\EmailLogin\EmailLoginBroker;
use Laragear\EmailLogin\EmailLoginIntent;
use Laragear\EmailLogin\Http\Routes;
use Tests\TestCase;

class EmailLoginControllerTest extends TestCase
{
    public function defineRoutes($router): void
    {
        $router->middleware('web')->group(fn() => Routes::register());
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

    public function test_send_mail(): void
    {
        $this->mock(EmailLoginBroker::class)->expects('create')->once();

        $this->post('/auth/email/send', [
            'email' => 'foo@bar.com',
            'remember' => 'on'
        ])
            ->assertSessionDoesntHaveErrors()
            ->assertSessionHas('sent', 'The login email has been sent to foo@bar.com.')
            ->assertRedirect('http://localhost');
    }

    public function test_cant_send_mail_if_already_authenticated(): void
    {
        $this->mock(EmailLoginBroker::class)->expects('create')->never();

        $this->be(User::find(1));

        $this->post('/auth/email/send', [
            'email' => 'foo@bar.com',
            'remember' => 'on'
        ])->assertRedirect();
    }

    public function test_shows_login_form(): void
    {
        $broker = $this->mock(EmailLoginBroker::class);
        $broker->expects('store')->with('array')->andReturnSelf();
        $broker->expects('get')->with(static::TOKEN)->andReturn(new EmailLoginIntent('web', 'test-id', true, '/', []));

        $this->get('/auth/email/login?token='.static::TOKEN.'&store=array')
            ->assertOk()
            ->assertViewIs('laragear::email-login.web.login')
            ->assertViewHas('token', static::TOKEN)
            ->assertViewHas('store', 'array');

        $this->assertGuest();
    }

    public function test_cant_show_login_form_if_authenticated(): void
    {
        $this->be(User::find(1));

        $this->get('/auth/email/login?token='.static::TOKEN.'&store=array')
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect();
    }

    public function test_logins_from_mail_request_form(): void
    {
        $broker = $this->mock(EmailLoginBroker::class);
        $broker->expects('store')->with('array')->andReturnSelf();
        $broker->expects('pull')->with(static::TOKEN)->andReturn(new EmailLoginIntent('web', '1', true, '/', []));

        $this->post('/auth/email/login?token='.static::TOKEN.'&store=array')
            ->assertRedirect('http://localhost');

        $this->assertAuthenticatedAs(User::find(1));
    }

    public function test_validation_error_if_store_invalid(): void
    {
        $broker = $this->mock(EmailLoginBroker::class);
        $broker->expects('store')->with('array')->andReturnSelf();
        $broker->expects('pull')->with(static::TOKEN)->andReturnNull();

        $this->post('/auth/email/login?token='.static::TOKEN.'&store=array')
            ->assertSessionHasErrors([
                'token' => 'The token is invalid or has expired.'
            ]);
    }

    public function test_validation_error_if_token_invalid(): void
    {
        $broker = $this->mock(EmailLoginBroker::class);
        $broker->expects('store')->with('array')->andReturnSelf();
        $broker->expects('pull')->with(static::TOKEN)->andReturnNull();

        $this->post('/auth/email/login?token='.static::TOKEN.'&store=array')
            ->assertSessionHasErrors([
                'token' => 'The token is invalid or has expired.'
            ]);
    }

    public function test_cant_login_from_mail_request_form_if_already_authenticated(): void
    {
        $this->be(User::find(1));

        $this->post('/auth/email/login?token='.static::TOKEN.'&store=array')
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect();
    }
}
