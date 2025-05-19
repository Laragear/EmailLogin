<?php

namespace Laragear\EmailLogin\Mails;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class LoginEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * The user that should be able to authenticate.
     */
    public Authenticatable $user;

    /**
     * The URL where the Mail points to the authentication.
     */
    public string $url;

    /**
     * The moment the Login Email stops being valid.
     */
    public Carbon $expiration;
}
