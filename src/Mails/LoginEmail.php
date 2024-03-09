<?php

namespace Laragear\EmailLogin\Mails;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LoginEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new notification instance.
     */
    final public function __construct(public Authenticatable $user, public string $url, string $view)
    {
        $this->view = $view;

        if ($this->user instanceof Model) {
            $this->user = $this->user->withoutRelations();
        }
    }

    /**
     * Build the message.
     *
     * @codeCoverageIgnore
     * @return $this
     */
    public function build(): static
    {
        return $this->markdown($this->view);
    }

    /**
     * Create a new Login Mail instance.
     */
    public static function make(Authenticatable $user, string $url, string $view): static
    {
        return new static($user, $url, $view);
    }
}
