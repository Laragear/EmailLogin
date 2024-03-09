<?php

namespace Tests\Http\Controllers\LoginMail;

use Illuminate\Auth\Events\Failed;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Laragear\EmailLogin\EmailLoginBroker;
use Laragear\EmailLogin\Http\Routes;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use function now;

class EmailLoginControllerTest extends TestCase
{
    public function defineRoutes($router): void
    {
        Routes::register();
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
    public function send_mail(): void
    {
        $this->post('/auth/email/send', [
            'email' => 'foo@bar.com',
            'remember' => 'on'
        ])
            ->assertSessionDoesntHaveErrors()
            ->assertSessionHas('login', 'The login email has been sent to foo@bar.com')
            ->assertRedirect('http://localhost');
    }

    #[Test]
    public function cant_send_mail_if_already_authenticated(): void
    {
        $this->be(User::find(1));

        $this->post('auth/email/send', [
            'email' => 'foo@bar.com',
            'remember' => 'on'
        ])->assertRedirect();
    }

    #[Test]
    public function shows_login_form(): void
    {
        $url = URL::temporarySignedRoute('auth.email.login', 60, ['id' => 1, 'guard' => 'web', 'remember' => 0]);

        $this->get($url)
            ->assertOk()
            ->assertViewIs('email-login::web.login');
    }

    #[Test]
    public function cant_show_login_form_if_authenticated(): void
    {
        $this->be(User::find(1));

        $this->get(URL::temporarySignedRoute('auth.email.login', 60, ['id' => 1, 'guard' => 'web', 'remember' => 0]))
            ->assertRedirect();
    }

    #[Test]
    public function logins_from_mail_request_form(): void
    {
        $this->mock(EmailLoginBroker::class)->expects('missing')->andReturnFalse();

        $url = URL::temporarySignedRoute('auth.email.login', 60, ['id' => 1, 'guard' => 'web', 'remember' => 0]);

        $this->post($url)->assertRedirect('http://localhost');

        $this->assertAuthenticatedAs(User::find(1));
    }

    #[Test]
    public function forbidden_login_from_mail_request_form_if_not_signed_properly(): void
    {
        $url = URL::route('auth.email.login', [
            'id' => 1,
            'guard' => 'web',
            'remember' => 0,
            'signature' => 'invalid',
            'expires' => now()->addMinute()->getTimestamp()
        ]);

        $this->post($url)->assertForbidden();
    }

    #[Test]
    public function forbidden_login_from_mail_request_form_if_expired(): void
    {
        $url = URL::temporarySignedRoute('auth.email.login', 60, ['id' => 1, 'guard' => 'web', 'remember' => 0]);

        $this->travelTo(now()->addMinute()->addSecond());

        $this->post($url)->assertForbidden();
    }

    #[Test]
    public function validation_error_if_guard_not_contained_in_app(): void
    {
        $url = URL::temporarySignedRoute('auth.email.login', 60, ['id' => 1, 'guard' => 'invalid', 'remember' => 0]);

        $this->post($url)->assertSessionHasErrors();
    }

    #[Test]
    public function aborts_login_if_user_doesnt_exists(): void
    {
        $this->mock(EmailLoginBroker::class)->expects('missing')->andReturnFalse();

        $event = Event::fake();

        $url = URL::temporarySignedRoute('auth.email.login', 60, ['id' => 2, 'guard' => 'web', 'remember' => 0]);

        $this->post($url)->assertSessionHasErrors();

        $event->assertDispatched(Failed::class, static function (Failed $event): bool {
            static::assertSame('web', $event->guard);
            static::assertSame(['id' => '2'], $event->credentials);
            static::assertNull($event->user);

            return true;
        });
    }

    #[Test]
    public function cant_login_from_mail_request_form_if_already_authenticated(): void
    {
        $this->be(User::find(1));

        $url = URL::temporarySignedRoute('auth.email.login', 60, ['id' => 1, 'guard' => 'web', 'remember' => 0]);

        $this->post($url)->assertRedirect();
    }
}
