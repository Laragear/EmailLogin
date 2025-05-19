<?php

namespace Laragear\EmailLogin;

use Closure;
use DateInterval;
use DateTimeInterface;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Failed;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Contracts\Auth\UserProvider as UserProviderContract;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Mail\Factory as MailerFactoryContract;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Contracts\Mail\Mailable as MailableContract;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Laragear\EmailLogin\Http\Requests\LoginByEmailRequest;
use Laragear\EmailLogin\Mails\LoginEmail;
use RuntimeException;
use function array_merge;
use function class_exists;
use function func_get_args;
use function func_num_args;
use function implode;
use function is_int;
use function method_exists;
use function now;
use function value;
use function with;

class EmailLoginBuilder
{
    /**
     * The callback that modifies or returns the mailable to use.
     *
     * @var \Closure(\Laragear\EmailLogin\Mails\LoginEmail):(void|\Illuminate\Contracts\Mail\Mailable)|null
     */
    protected ?Closure $mailable = null;

    /**
     * The expiration amount.
     */
    public DateTimeInterface|int|string $expiration;

    /**
     * The guard name to use for authentication.
     */
    protected string $guard;

    /**
     * If the user should be remembered on the device.
     */
    protected bool $remember = false;

    /**
     * Add additional credentials to locate the proper User.
     *
     * @var array<string|int, (\Closure(\Illuminate\Contracts\Database\Query\Builder|\Illuminate\Contracts\Database\Eloquent\Builder):void)|mixed>
     */
    protected array $credentials = [];

    /**
     * The destination closure that returns where the email should point to.
     *
     * @var \Closure(array $parameters):string
     */
    protected Closure $destination;

    /**
     * The destination parameters to include.
     */
    protected array $destinationParameters = [];

    /**
     * The store to use by the Email Login Broker.
     *
     * @var string|null
     */
    protected ?string $store = null;

    /**
     * A callback where the email sent is executed
     *
     * @var \Closure():bool
     */
    protected Closure $execute;

    /**
     * The additional metadata to include in the underlying Email Login Intent.
     */
    protected Arrayable|array $metadata = [];

    /**
     * Create a new Builder instance.
     */
    public function __construct(
        protected Container $container,
        protected Repository $config,
        protected Factory $auth,
        protected Redirector $redirector,
        protected Request $request,
    ) {
        $this->expiration = $this->config->get('email-login.expiration');
        $this->guard = $this->config->get('email-login.guard') ?: $this->config->get('auth.defaults.guard');
        $this->execute = $this->attempt(...);

        $this->withRoute($this->config->get('email-login.route.name'));
    }

    /**
     * Sets the store to use for saving the Login Request token.
     *
     * @return $this
     */
    public function withStore(?string $store): static
    {
        $this->store = $store;

        return $this;
    }

    /**
     * Credentials to locate the user.
     *
     * @param  (\Closure(\Illuminate\Contracts\Database\Query\Builder|\Illuminate\Contracts\Database\Eloquent\Builder):void)|string|array<string|int, (\Closure(\Illuminate\Contracts\Database\Query\Builder|\Illuminate\Contracts\Database\Eloquent\Builder):void)|mixed>  $credentials
     * @return $this
     */
    public function withCredentials(Closure|string|array $credentials): static
    {
        if (func_num_args() > 1) {
            $credentials = func_get_args();
        }

        $this->credentials = Arr::wrap($credentials);

        return $this;
    }

    /**
     * Sets the minutes to keep the Email Login intent alive.
     *
     * @return $this
     */
    public function withExpiration(DateTimeInterface|int|string $minutes): static
    {
        $this->expiration = $minutes;

        return $this;
    }

    /**
     * Changes the location of the "remember" in the request.
     *
     * @return $this
     */
    public function withRemember(Closure|string|bool $condition = true): static
    {
        $this->remember = is_string($condition)
            ? $this->request->boolean($condition)
            : (bool) value($condition, $this->request);

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
     * Sets the path where the user should log in.
     *
     * @return $this
     */
    public function withPath(string $path, array $extra = []): static
    {
        $this->destination = function (array $parameters) use ($path): string {
            return $this->container->make('url')->to($path, $parameters);
        };

        return $this->withParameters($extra);
    }

    /**
     * Sets the path where the user should log in.
     *
     * @return $this
     */
    public function withQuery(string $path, array $query = []): static
    {
        $this->destination = function (array $parameters) use ($path): string {
            /** @var \Illuminate\Contracts\Routing\UrlGenerator $url */
            $url = $this->container->make('url');

            // @codeCoverageIgnoreStart
            return method_exists($url, 'query')  // @phpstan-ignore-line
                ? $url->query($path, $parameters)
                : $url->to($path . ($parameters ? '?' . Arr::query($parameters) : ''));
            // // @codeCoverageIgnoreEnd
        };

        return $this->withParameters($query);
    }

    /**
     * Sets the action where the user should log in.
     *
     * @return $this
     */
    public function withAction(string|array $action, array $parameters = []): static
    {
        $this->destination = function (array $parameters) use ($action): string {
            return $this->container->make('url')->action($action, $parameters);
        };

        return $this->withParameters($parameters);
    }

    /**
     * Sets the route name where the user should log in.
     *
     * @return $this
     */
    public function withRoute(string $name, array $parameters = []): static
    {
        $this->destination = function (array $parameters) use ($name): string {
            return $this->container->make('url')->route($name, $parameters);
        };

        return $this->withParameters($parameters);
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
     * Configure the Mailable using a callback or use another.
     *
     * @param  \Closure(\Laragear\EmailLogin\Mails\LoginEmail):(void|\Illuminate\Contracts\Mail\Mailable)|\Illuminate\Contracts\Mail\Mailable|class-string<\Illuminate\Contracts\Mail\Mailable>  $mailable
     * @return $this
     */
    public function withMailable(Closure|MailableContract|string $mailable): static
    {
        // If the developer is using a custom Mailable as a class string, allow the app to resolve it.
        if (is_string($mailable) && class_exists($mailable)) {
            $mailable = fn(): MailableContract => $this->container->make($mailable);
        }

        // If it's a Mailable instance, we will just return the mailable.
        if ($mailable instanceof MailableContract) {
            $mailable = fn(): MailableContract => $mailable;
        }

        // Otherwise, store the callback as-is.
        $this->mailable = $mailable;

        return $this;
    }

    /**
     * Throttles the login request using the given login.
     *
     * @return $this
     */
    public function withThrottle(
        DateTimeInterface|DateInterval|string|int $duration,
        ?string $store = null,
        ?string $key = null
    ): static {
        // We will replace the execution callback with one that throttles.
        $this->execute = function () use ($duration, $store, $key): bool {
            $key = implode('|', [
                $this->config->get('email-login.cache.prefix'),
                $this->config->get('email-login.throttle.prefix'),
                $key ?? $this->getRequestFingerprint()
            ]);

            // Use the "remember" method to attempt. By returning "null", we will be able to try again.
            return $this->container->make('cache')
                ->store($store)
                ->remember($key, $duration, function (): ?bool {
                    return $this->attempt() ?: null;
                });
        };

        return $this;
    }

    /**
     * Return the Request fingerprint if available.
     */
    protected function getRequestFingerprint(): string
    {
        try {
            return $this->request->fingerprint();
        } catch (RuntimeException $e) {
            throw new RuntimeException('A key is required for this request to be throttled.', previous: $e);
        }
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
     * Sends to send the email.
     */
    public function send(): bool
    {
        return ($this->execute)();
    }

    /**
     * Attempt to send the Login Email.
     */
    protected function attempt(): bool
    {
        // If the user has set its own set of credentials, use it.
        $credentials = $this->retrieveCredentials();

        /** @var \Illuminate\Contracts\Events\Dispatcher $event */
        $event = $this->container->make('events');

        // Send the Attempting event with the credentials.
        $event->dispatch(new Attempting($this->guard, $credentials, $this->remember));

        // If we can't find the user, return false.
        if (!$user = $this->getUserProvider()->retrieveByCredentials($credentials)) {
            $event->dispatch(new Failed($this->guard, null, $credentials));

            return false;
        }

        $token = $this->getTokenForEmailLoginIntent($user);

        // @phpstan-ignore-next-line
        $mailable = with($mailable = $this->buildMailable($user, $token), $this->mailable) ?? $mailable;

        if (method_exists($mailable, 'onConnection')) {
            $mailable->onConnection($this->config->get('email-login.mail.connection'));
        }

        if (method_exists($mailable, 'onQueue')) {
            $mailable->onQueue($this->config->get('email-login.mail.queue'));
        }

        $this->container->make(MailerFactoryContract::class)
            ->mailer($this->config->get('email-login.mail.mailer'))
            ->send($mailable);

        return true;
    }

    /**
     * Build the default mailable.
     */
    protected function buildMailable(Authenticatable $user, string $token): Mailable
    {
        $expiration = is_int($this->expiration)
            ? now()->addMinutes($this->expiration)
            : Carbon::parse($this->expiration);

        $mailable = $this->container->make(LoginEmail::class);
        $mailable->user = $user;
        $mailable->url = $this->buildUrl($token);
        $mailable->expiration = $expiration;

        $mailable->to($user);
        $mailable->markdown($this->config->get('email-login.mail.markdown'));

        return $mailable;
    }

    /**
     * Returns the Email Broker store to use.
     */
    protected function getEmailBrokerStore(): string
    {
        return $this->store
            ?? $this->config->get('email-login.cache.store')
            ?? $this->config->get('cache.default');
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
     * Builds the login email url and returns it.
     */
    protected function buildUrl(string $token): string
    {
        // Override any conflicting query parameter when building the url.
        return ($this->destination)(array_merge($this->destinationParameters, [
            LoginByEmailRequest::TOKEN_KEY => $token,
            LoginByEmailRequest::STORE_KEY => $this->getEmailBrokerStore(),
        ]));
    }

    /**
     * Stores the Email Login Intent, so it can later be pulled out at login time with the returned token string.
     */
    protected function getTokenForEmailLoginIntent(Authenticatable $user): string
    {
        return $this->container->make(EmailLoginBroker::class)
            ->store($this->getEmailBrokerStore())
            ->create(
                $this->guard,
                $user->getAuthIdentifier(),
                $this->expiration,
                $this->remember,
                $this->redirector->getIntendedUrl(),
                $this->metadata
            );
    }

    /**
     * Returns the credentials to use by the developer.
     */
    protected function retrieveCredentials(): array
    {
        $credentials = [];

        // Add the new credentials from the user issued to the array.
        foreach ($this->credentials as $key => $value) {
            // Find the value of the request key only if the key is an array index (integer).
            if (is_int($key) && !$value instanceof Closure && null !== $input = $this->request->input($value)) {
                [$key, $value] = [$value, $input];
            }

            $credentials[$key] = $value;
        }

        return $credentials;
    }
}
