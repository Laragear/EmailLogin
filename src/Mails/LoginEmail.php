<?php

namespace Laragear\EmailLogin\Mails;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Traits\Tappable;
use function config;
use function url;

class LoginEmail extends Mailable implements ShouldQueue
{
    use Queueable;
    use Tappable;

    /**
     * Create a new notification instance.
     */
    public function __construct(Authenticatable $user, ?string $url = null, ?string $view = null)
    {
        if ($user instanceof Model) {
            $user = $user->withoutRelations();
        }

        $this->to = [$user];

        $this->view = $view ?? config('email-login.route.view');
        $this->viewData = [
            'user' => $user,
            'url' => $this->url ?? url(config('email-login.route.name'))
        ];
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
