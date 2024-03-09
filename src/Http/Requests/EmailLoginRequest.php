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
use Illuminate\Contracts\Mail\Factory as FactoryContract;
use Illuminate\Contracts\Mail\Mailable as MailableContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Laragear\EmailLogin\EmailLoginBroker;
use Laragear\EmailLogin\Mails\LoginEmail;
use function __;
use function array_merge;
use function is_int;
use function is_string;
use function with;

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
     *
     * @var string
     */
    protected string $destinationRoute;

    /**
     * The destination query.
     *
     * @var array<string, string>
     */
    protected array $destinationQuery = [];

    /**
     * The rules to use to validate the email before sending.
     *
     * @var array<string|\Illuminate\Contracts\Validation\ValidationRule>
     */
    protected array $rules = ['email'];

    /**
     * The guard to use to log in the user through the email.
     *
     * @var string
     */
    protected string $guard;

    /**
     * The key where the email string resides in this request.
     *
     * @var string
     */
    protected string $emailKey;

    /**
     * The key where the remember input resides in this request.
     *
     * @var string
     */
    protected string $rememberKey = 'remember';

    /**
     * The application configuration.
     */
    protected ConfigContract $config;

    /**
     * The amount of time the Login Email intent should last.
     */
    protected DateTimeInterface|DateInterval|int $minutes;

    /**
     * Add additional credentials to locate the proper User.
     *
     * @var array<string, (\Closure(\Illuminate\Contracts\Database\Query\Builder|\Illuminate\Contracts\Database\Eloquent\Builder):void)|mixed>
     */
    protected array $credentials = [];

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        // Instead of validating the request, we will set some configuration defaults first;
        $this->config = $this->container->make('config');

        $this->minutes = 60 * (int) $this->config->get('email-login.minutes');
        $this->destinationRoute = $this->config->get('email-login.route.name');
        $this->guard = $this->config->get('email-login.guard') ?: $this->config->get('auth.defaults.guard');
        $this->emailKey = $this->config->get("email-login.guards.$this->guard");
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string,string>
     */
    public function rules(): array
    {
        return [$this->emailKey => 'required|string'];
    }

    /**
     * Configure the mailable using a callback, or use another.
     *
     * @param  \Closure(\Laragear\EmailLogin\Mails\LoginEmail):(void|\Illuminate\Contracts\Mail\Mailable)|\Illuminate\Contracts\Mail\Mailable|class-string<\Illuminate\Contracts\Mail\Mailable>  $mailable
     * @return $this
     */
    public function mailable(Closure|MailableContract|string $mailable): static
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
     * Sets the query parameters to include in the Email Login URL.
     *
     * @return $this
     */
    public function withQuery(array $query): static
    {
        return $this->toRoute($this->destinationRoute, $query);
    }

    /**
     * Sets the mail link destination to the given route.
     *
     * @return $this
     */
    public function toRoute(string $route, array $query = null): static
    {
        $this->destinationRoute = $route;
        $this->destinationQuery = $query ?? $this->destinationQuery;

        return $this;
    }

    /**
     * Defines the guard to use.
     *
     * @return $this
     */
    public function guard(string $guard): static
    {
        $this->guard = $guard;

        return $this;
    }

    /**
     * Changes the location of the remember in the request.
     *
     * @return $this
     */
    public function rememberKey(string $rememberKey): static
    {
        $this->rememberKey = $rememberKey;

        return $this;
    }

    /**
     * Sets the minutes to keep the Email Login intent alive.
     *
     * @return $this
     */
    public function aliveFor(DateTimeInterface|DateInterval|int $minutes): static
    {
        $this->minutes = is_int($minutes) ? $minutes * 60 : $minutes;

        return $this;
    }

    /**
     * Add additional credentials to locate the user.
     *
     * @param  array<string, (\Closure(\Illuminate\Contracts\Database\Query\Builder|\Illuminate\Contracts\Database\Eloquent\Builder):void)|mixed>  $credentials
     * @return $this
     */
    public function withCredentials(array $credentials): static
    {
        $this->credentials = $credentials;

        return $this;
    }

    /**
     * Sends the Login Email.
     */
    public function send(): RedirectResponse
    {
        $this->dispatch($this->validated());

        return $this->redirector->back();
    }

    /**
     * Dispatches the email to the user, if found.
     */
    protected function dispatch(array $credentials): void
    {
        $credentials = array_merge($credentials, $this->credentials);

        /** @var \Illuminate\Contracts\Events\Dispatcher $event */
        $event = $this->container->make('events');

        $event->dispatch(new Attempting($this->guard, $credentials, $this->boolean($this->rememberKey)));

        // If we can't find the user, bail out.
        if (!$user = $this->getUserProvider()->retrieveByCredentials($credentials)) {
            $event->dispatch(new Failed($this->guard, null, $credentials));

            throw ValidationException::withMessages([
                $this->emailKey => __('validation.email', ['attribute' => $this->emailKey])
            ]);
        }

        $this->storeEmailLoginIntent($user);

        $mail = LoginEmail::make($user, $this->buildUrl($user), $this->config->get('email-login.mail.view'))
            ->onConnection($this->config->get('email-login.mail.connection'))
            ->onQueue($this->config->get('email-login.mail.queue'));

        $this->container->make(FactoryContract::class)
            ->mailer($this->config->get('email-login.mail.mailer'))
            ->to($user)
            ->send(with($mail, $this->mailable) ?: $mail);
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
    protected function buildUrl(Authenticatable $user): string
    {
        $params = [
            'guard' => $this->guard,
            'id' => $user->getAuthIdentifier(),
            'remember' => $this->boolean($this->rememberKey),
        ];

        // Add the intended url if it exists. Only add the path to avoid serialization issues.
        if ($intended = parse_url($this->redirector->intended()->getTargetUrl(), PHP_URL_PATH)) {
            $params['intended'] = $intended;
        }

        return $this->container->make('url')->temporarySignedRoute(
            $this->destinationRoute, $this->minutes, array_merge($this->destinationQuery, $params)
        );
    }

    /**
     * Stores the Email Login Intent, so it can later be pulled out at login time.
     */
    protected function storeEmailLoginIntent(Authenticatable $user): void
    {
        $this->container->make(EmailLoginBroker::class)->register(
            $this->guard, $user->getAuthIdentifier(), $this->minutes
        );
    }
}
