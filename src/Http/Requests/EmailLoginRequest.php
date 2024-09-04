<?php

namespace Laragear\EmailLogin\Http\Requests;

use Closure;
use DateInterval;
use DateTimeInterface;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Failed;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider as UserProviderContract;
use Illuminate\Contracts\Config\Repository as ConfigContract;
use Illuminate\Contracts\Mail\Factory as MailerFactoryContract;
use Illuminate\Contracts\Mail\Mailable as MailableContract;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Laragear\EmailLogin\EmailLoginBroker;
use Laragear\EmailLogin\Mails\LoginEmail;
use function __;
use function array_merge;
use function back;
use function implode;
use function is_int;
use function is_string;
use function parse_url;
use function value;
use function with;
use const PHP_URL_PATH;

class EmailLoginRequest extends FormRequest
{
    /**
     * The callback that returns the mailable to use.
     *
     * @var \Closure(\Laragear\EmailLogin\Mails\LoginEmail):(void|\Illuminate\Contracts\Mail\Mailable)|null
     */
    protected ?Closure $mailable = null;

    /**
     * The destination route name.
     */
    protected Closure $destination;

    /**
     * The destination parameters to include.
     */
    protected array $destinationParameters = [];

    /**
     * The key where the remember input resides in this request.
     *
     * @var string
     */
    protected bool $shouldRemember = false;

    /**
     * The amount of time the Login Email intent should last.
     */
    protected DateTimeInterface|int|string $expiration;

    /**
     * The guard to use to log in the user through the email.
     *
     * @var string
     */
    protected string $guard;

    /**
     * The application configuration.
     */
    protected ConfigContract $config;

    /**
     * Add additional credentials to locate the proper User.
     *
     * @var array<string, (\Closure(\Illuminate\Contracts\Database\Query\Builder|\Illuminate\Contracts\Database\Eloquent\Builder):void)|mixed>
     */
    protected array $credentials = [];

    /**
     *
     * The additional metadata to include in the underlying Email Login Intent.
     */
    protected Arrayable|array $metadata = [];

    /**
     * The data used to throttle the request, if required.
     */
    protected ?Closure $throttle = null;

    /**
     * Validate the class instance.
     *
     * @return void
     */
    public function validateResolved(): void
    {
        // Instead of validating the request, we will set configuration defaults.
        $this->config = $this->container->make('config');

        $this->expiration = 60 * (int) $this->config->get('email-login.expiration');
        $this->guard = $this->config->get('email-login.defaults.guard') ?: $this->config->get('auth.defaults.guard');

        $this->withRoute($this->config->get('email-login.route.name'));
    }

    /**
     * Add additional credentials to locate the user.
     *
     * @return $this
     */
    public function withCredentials(array $credentials): static
    {
        $this->credentials = $credentials;

        return $this;
    }

    /**
     * Sets the minutes to keep the Email Login intent alive.
     *
     * @return $this
     */
    public function expiresAt(DateTimeInterface|int|string $minutes): static
    {
        $this->expiration = $minutes;

        return $this;
    }

    /**
     * Changes the location of the remember in the request.
     *
     * @return $this
     */
    public function withRemember(Closure|string|bool $condition = 'remember'): static
    {
        $this->shouldRemember = is_string($condition) ? $this->boolean($condition) : (bool) value($condition, $this);

        return $this;
    }

    /**
     * Defines the guard to use.
     *
     * @return $this
     */
    public function withGuard(string $guard): static
    {
        $this->guard = $guard;

        return $this;
    }

    /**
     * Sets the raw string where the user should log in.
     *
     * @return $this
     */
    public function withRawLocation(Closure|string $callback): static
    {
        if (is_string($callback)) {
            $callback = fn() => $callback;
        }

        $this->destination = $callback;

        return $this;
    }

    /**
     * Sets the path where the user should log in.
     *
     * @return $this
     */
    public function withPath(string $path, array $extra = []): static
    {
        return $this->withParameters($extra)->withRawLocation(
            fn (array $parameters): string => $this->container->make('url')->to($path, $parameters)
        );
    }

    /**
     * Sets the action where the user should log in.
     *
     * @return $this
     */
    public function withAction(string|array $action, array $parameters = []): static
    {
        return $this->withParameters($parameters)->withRawLocation(
            fn (array $parameters): string => $this->container->make('url')->action($action, $parameters)
        );
    }

    /**
     * Sets the route name where the user should log in.
     *
     * @return $this
     */
    public function withRoute(string $name, array $parameters = []): static
    {
        return $this->withParameters($parameters)->withRawLocation(
            fn (array $parameters): string => $this->container->make('url')->route($name, $parameters)
        );
    }

    /**
     * Sets the query parameters to include in the Email Login URL.
     *
     * @return $this
     */
    public function withParameters(array $query): static
    {
        $this->destinationParameters = $query;

        return $this;
    }

    /**
     * Configure the mailable using a callback, or use another.
     *
     * @param  \Closure(\Laragear\EmailLogin\Mails\LoginEmail):(void|\Illuminate\Contracts\Mail\Mailable)|\Illuminate\Contracts\Mail\Mailable|class-string<\Illuminate\Contracts\Mail\Mailable>  $mailable
     * @return $this
     */
    public function withMailable(Closure|MailableContract|string $mailable): static
    {
        // If the dev uses a custom mailable as a class string, allow the app to resolve it.
        if (is_string($mailable)) {
            $mailable = fn(): MailableContract => $this->container->make($mailable);
        }

        // If it's an object, we will just return the mailable.
        if ($mailable instanceof MailableContract) {
            $mailable = fn(): MailableContract => $mailable;
        }

        // Otherwise, just store the callback as-is.
        $this->mailable = $mailable;

        return $this;
    }

    /**
     * Throttles the login request using the given login.
     *
     * @return $this
     */
    public function throttleBy(
        DateTimeInterface|DateInterval|string|int $duration,
        string $store = null,
        string $key = null
    ): static {
        $this->throttle = function () use ($duration, $store, $key): void {
            $key = implode('|', [
                $this->config->get('email-login.cache.prefix'),
                $this->config->get('email-login.throttle.prefix'),
                $this->throttle['key'] ?? $this->fingerprint()
            ]);

            $this->container->make('cache')
                ->store($this->throttle['store'])
                ->remember($key, $this->throttle['expire'], $this->send(...));
        };

        return $this;
    }

    /**
     * Adds additional metadata to the underlying Email Login intent.
     *
     * @return $this
     */
    public function withMetadata(Arrayable|array $metadata): static
    {
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * Sends the Login Email and returns a redirection back.
     */
    public function sendAndBack($status = 302, $headers = [], $fallback = false): RedirectResponse
    {
        $this->send();

        return back($status, $headers, $fallback);
    }

    /**
     * Sends to send the email.
     */
    public function send(): void
    {
        empty($this->throttle) ? $this->attempt() : ($this->throttle)();
    }

    /**
     * Attempt to send the Login Email.
     */
    protected function attempt(): bool
    {
        // If the user has set its own set of credentials, use it.
        $credentials = $this->retrieveCredentials() ?: $this->validated();

        /** @var \Illuminate\Contracts\Events\Dispatcher $event */
        $event = $this->container->make('events');

        $event->dispatch(new Attempting($this->guard, $credentials, $this->shouldRemember));

        // If we can't find the user, bail out.
        if (!$user = $this->getUserProvider()->retrieveByCredentials($credentials)) {
            $event->dispatch(new Failed($this->guard, null, $credentials));

            throw ValidationException::withMessages(['email' => __('validation.email', ['attribute' => 'email'])]);
        }

        $token = $this->getTokenForEmailLoginIntent($user);

        $mail = LoginEmail::make($user, $this->buildUrl($token), $this->config->get('email-login.mail.view'))
            ->when($connection = $this->config->get('email-login.mail.connection'))->onConnection($connection)
            ->when($queue = $this->config->get('email-login.mail.queue'))->onQueue($queue);

        $this->container->make(MailerFactoryContract::class)
            ->mailer($this->config->get('email-login.mail.mailer'))
            ->to($user)
            ->send(with($mail, $this->mailable) ?: $mail);

        return true;
    }

    /**
     * Returns the User Provider used by the Authentication Guard.
     */
    protected function getUserProvider(): UserProviderContract
    {
        $guard = $this->container->make('auth')->guard($this->guard);

        if (method_exists($guard, 'getProvider')) {
            return $guard->getProvider();
        }

        return $this->container->make('auth')->createUserProvider(
            $this->config->get("auth.guards.$this->guard.provider")
        );
    }

    /**
     * Builds the login email.
     */
    protected function buildUrl(string $token): string
    {
        // Override any conflicting query parameter when building the url.
        return ($this->destination)(array_merge($this->destinationParameters, [
            LoginByEmailRequest::INTENT_KEY => $token,
            LoginByEmailRequest::STORE_KEY => $this->guard,
        ]));
    }

    /**
     * Stores the Email Login Intent, so it can later be pulled out at login time.
     */
    protected function getTokenForEmailLoginIntent(Authenticatable $user): string
    {
        return $this->container->make(EmailLoginBroker::class)->create(
            $this->guard,
            $user->getAuthIdentifier(),
            $this->expiration,
            $this->shouldRemember,
            $this->redirector->intended()->getTargetUrl(),
            $this->metadata
        );
    }

    /**
     * Returns the credentials to use by the developer.
     */
    protected function retrieveCredentials(): array
    {
        $credentials = [];

        foreach ($this->credentials as $key => $value) {
            // Find the key only if the key is an integer.
            if (is_int($key) && !$value instanceof Closure && null !== $input = $this->input($value)) {
                $credentials[$value] = $input;
            }

            $credentials[$key] = $value;
        }

        return $credentials;
    }
}
