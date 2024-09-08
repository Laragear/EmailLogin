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

        $mailable = LoginEmail::make(
            User::make()->forceFill(['name' => 'john doe', 'email' => 'test@email.com']),
            'foo/bar',
            'laragear::email-login.mail.login',
            now()->addMinutes(60)
        );

        $mailable->assertHasTo('test@email.com')
            ->assertSeeInHtml('foo/bar')
            ->assertSeeInText('john doe')
            ->assertSeeInText('Login to Laravel')
            ->assertSeeInText('This link will last for 1 hour');
    }
}
