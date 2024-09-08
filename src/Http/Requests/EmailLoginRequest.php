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
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Contracts\Mail\Mailable as MailableContract;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Foundation\Precognition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Laragear\EmailLogin\EmailLoginBroker;
use Laragear\EmailLogin\Mails\LoginEmail;
use function array_merge;
use function back;
use function implode;
use function is_int;
use function is_string;
use function method_exists;
use function now;
use function tap;
use function value;
use function with;

class EmailLoginRequest extends FormRequest
{
    /**
     * Default rules to use when not validating.
     */
    public const RULES = ['email' => 'required|email'];

    /**
     * The callback that modifies or returns the mailable to use.
     *
     * @var \Closure(\Laragear\EmailLogin\Mails\LoginEmail):(void|\Illuminate\Contracts\Mail\Mailable)|null
     */
    protected ?Closure $mailable = null;

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
     * @var array<string, (\Closure(\Illuminate\Contracts\Database\Query\Builder|\Illuminate\Contracts\Database\Eloquent\Builder):void)|mixed>|null
     */
    protected ?array $credentials = null;

    /**
     *
     * The additional metadata to include in the underlying Email Login Intent.
     */
    protected Arrayable|array $metadata = [];

    /**
     * A callback where the email sent is executed
     *
     * @var \Closure():bool)
     */
    protected Closure $execute;

    /**
     * Validate the class instance.
     *
     * @return void
     */
    public function validateResolved(): void
    {
        // Instead of validating the request, we will set configuration defaults.
        $this->config = $this->container->make('config');

        $this->expiration = $this->config->get('email-login.expiration');
        $this->guard = $this->config->get('email-login.guard') ?: $this->config->get('auth.defaults.guard');

        $this->shouldRemember = $this->boolean('remember');

        $this->withRoute($this->config->get('email-login.route.name'));

        $this->execute = $this->attempt(...);
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
    public function withExpiration(DateTimeInterface|int|string $minutes): static
    {
        $this->expiration = $minutes;

        return $this;
    }

    /**
     * Changes the location of the remember in the request.
     *
     * @return $this
     */
    public function withRemember(Closure|string|bool $condition): static
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
        // We will replace the execution callback for one that uses the remember.
        $this->execute = function () use ($duration, $store, $key): bool {
            $key = implode('|', [
                $this->config->get('email-login.cache.prefix'),
                $this->config->get('email-login.throttle.prefix'),
                $key ?? $this->fingerprint()
            ]);

            // Execute the send method but return "null" if it fails so it doesn't get throttled.
            return $this->container->make('cache')
                ->store($store)
                ->remember($key, $duration, fn (): bool => $this->attempt() || true);
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
        $credentials = $this->retrieveCredentials() ?? $this->validated();

        /** @var \Illuminate\Contracts\Events\Dispatcher $event */
        $event = $this->container->make('events');

        // Send the Attempting event with the credentials.
        $event->dispatch(new Attempting($this->guard, $credentials, $this->shouldRemember));

        // If we can't find the user, return false.
        if (!$user = $this->getUserProvider()->retrieveByCredentials($credentials)) {
            $event->dispatch(new Failed($this->guard, null, $credentials));

            return false;
        }

        $token = $this->getTokenForEmailLoginIntent($user);

        $mailable = with($mailable = $this->buildMailable($user, $token), $this->mailable) ?? $mailable;

        if (method_exists($mailable, 'onConnection') && method_exists($mailable, 'onQueue')) {
            $mailable
                ->onConnection($this->config->get('email-login.mail.connection'))
                ->onQueue($this->config->get('email-login.mail.queue'));
        }

        $this->container->make(MailerFactoryContract::class)
            ->mailer($this->config->get('email-login.mail.mailer'))
            ->send($mailable);

        return true;
    }

    /**
     * Validate the Request using a set of rules.
     */
    public function validate(array $rules, ...$params): array
    {
        // We have to re-implement the macro to re-use the validator and validated data.
        $validator = $this->container->make(ValidationFactory::class)->make($this->all(), $rules, ...$params);

        // @codeCoverageIgnoreStart
        if ($this->isPrecognitive()) {
            $validator
                ->after(Precognition::afterValidationHook($this))
                ->setRules(
                    $this->filterPrecognitiveRules($validator->getRulesWithoutPlaceholders())
                );
        }
        // @codeCoverageIgnoreEnd

        return tap($validator, $this->setValidator(...))->validate();
    }

    /**
     * Get the validated data from the request.
     *
     * @param  array|int|string|null  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function validated($key = null, $default = null)
    {
        if (!$this->validator) {
            $this->validate(static::RULES);
        }

        return parent::validated($key, $default);
    }

    /**
     * Build the default mailable.
     */
    protected function buildMailable(Authenticatable $user, string $token): Mailable
    {
        if ($user instanceof Model) {
            $user = $user->withoutRelations();
        }

        $expiration = is_int($this->expiration)
            ? now()->addMinutes($this->expiration)
            : Carbon::parse($this->expiration);

        $mailable = $this->container->make(LoginEmail::class, [
            'user' => $user,
            'url' => $this->buildUrl($token),
            'expiration' => $expiration
        ]);

        $mailable->to($user);

        $mailable->markdown($this->config->get('email-login.mail.markdown'));

        return $mailable;
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
            LoginByEmailRequest::STORE_KEY => $this->guard,
        ]));
    }

    /**
     * Stores the Email Login Intent, so it can later be pulled out at login time with the returned token string.
     */
    protected function getTokenForEmailLoginIntent(Authenticatable $user): string
    {
        return $this->container->make(EmailLoginBroker::class)->create(
            $this->guard,
            $user->getAuthIdentifier(),
            $this->expiration,
            $this->shouldRemember,
            $this->redirector->getIntendedUrl(),
            $this->metadata
        );
    }

    /**
     * Returns the credentials to use by the developer.
     */
    protected function retrieveCredentials(): ?array
    {
        if (!$this->credentials) {
            return null;
        }

        $credentials = [];

        $i = 0;

        foreach ($this->credentials as $key => $value) {
            // Find the value of the request key only if the key is an array index (integer).
            if ($i === $key && !$value instanceof Closure && null !== $input = $this->input($value)) {
                [$key, $value] = [$value, $input];
            }

            $credentials[$key] = $value;

            ++$i;
        }

        return $credentials;
    }
}
