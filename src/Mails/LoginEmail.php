<?php

namespace Laragear\EmailLogin\Mails;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Traits\Tappable;
use function config;
use function url;

class LoginEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new mailable instance.
     */
    public function __construct(public Authenticatable $user, public string $url, public Carbon $expiration)
    {
        //
    }

    /**
     * Create a new Login Mail instance.
     */
    public static function make(Authenticatable $user, string $url, string $markdown, \DateTimeInterface $expiration): static
    {
        if ($user instanceof Model) {
            $user = $user->withoutRelations();
        }

        // @phpstan-ignore-next-line
        return (new static($user, $url, Carbon::parse($expiration)))->to($user)->markdown($markdown);
    }
}
