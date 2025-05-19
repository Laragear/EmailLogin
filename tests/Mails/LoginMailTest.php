<?php

namespace Tests\Mails;

use Illuminate\Foundation\Auth\User;
use Laragear\EmailLogin\Mails\LoginEmail;
use Tests\TestCase;
use function now;

class LoginMailTest extends TestCase
{
    public function test_builds_mailable(): void
    {
        $this->freezeSecond();

        $mailable = new LoginEmail();
        $mailable->user = User::make()->forceFill(['name' => 'john doe', 'email' => 'test@email.com']);
        $mailable->to($mailable->user);
        $mailable->expiration = now()->addMinutes(19);
        $mailable->markdown('laragear::email-login.mail.login');
        $mailable->url = 'foo/bar';

        $mailable->assertHasTo('test@email.com')
            ->assertSeeInHtml('foo/bar')
            ->assertSeeInText('john doe')
            ->assertSeeInText('Login to Laravel')
            ->assertSeeInText('This link will last for 19 minutes');
    }
}
