<?php

namespace Laragear\EmailLogin\Http\Requests;

use Closure;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;
use Laragear\EmailLogin\EmailLoginBroker;
use Laragear\EmailLogin\EmailLoginIntent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use function __;
use function data_get;
use function in_array;

class LoginByEmailRequest extends FormRequest
{
    /**
     * The name of the request query key where the login token should be.
     */
    public const TOKEN_KEY = 'token';

    /**
     * The name of the request query key where the guard name should be.
     */
    public const STORE_KEY = 'store';

    /**
     * If the session should be destroyed on regeneration
     */
    public static bool $destroyOnRegeneration = false;

    /**
     * The Token Action of this Login Email request.
     */
    protected ?EmailLoginIntent $intent;

    /**
     * The Email Login Broker instance.
     */
    protected EmailLoginBroker $broker;

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            static::STORE_KEY => ['required', 'string', 'bail'],
            static::TOKEN_KEY => ['required', 'string', 'bail', $this->validateToken(...)]
        ];
    }

    /**
     * Validates the incoming token.
     */
    protected function validateToken(string $attribute, mixed $value, Closure $fail): void
    {
        // If the store key doesn't exist, it will throw an "InvalidArgumentException".
        // We will capture that exception, and instead of throwing something, we will
        // just set the intent as not found. This also obfuscates the cache stores.
        try {
            $this->intent = $this->isListing()
                ? $this->broker()->get($value)
                : $this->broker()->pull($value);
        } catch (InvalidArgumentException) {
            $this->intent = null;
        }

        if (!$this->intent) {
            $fail(__('The :attribute is invalid or has expired.', ['attribute' => $attribute]));
        }
    }

    /**
     * Check if the request is a GET or HEAD request.
     */
    protected function isListing(): bool
    {
        return in_array($this->method(), ['GET', 'HEAD'], true);
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator): never
    {
        // Abort if we're showing a view through a `GET` method.
        if ($this->isListing()) {
            throw new HttpException(419, __('Page Expired'));
        }

        parent::failedValidation($validator);
    }

    /**
     * Return the Email Login Broker.
     */
    protected function broker(): EmailLoginBroker
    {
        return $this->broker ??= $this->container->make(EmailLoginBroker::class)->store(
            $this->input(static::STORE_KEY)
        );
    }

    /**
     * Handle a passed validation attempt.
     */
    protected function passedValidation(): void
    {
        // Only log in the user if this is a form submission.
        if ($this->isMethod('POST')) {
            $this->login(
                $this->container->make('auth')->guard($this->intent->guard),
                $this->intent->id,
                $this->intent->remember
            );

            $this->session()->regenerate(static::$destroyOnRegeneration);

            // Add the intended URL to the session store.
            $this->container->make('session')->put('url.intended', $this->intent->intended);
        }
    }

    /**
     * Proceed to log in the user after a successful form submission.
     */
    protected function login(StatefulGuard $guard, mixed $id, bool $remember): void
    {
        $guard->loginUsingId($id, $remember);
    }

    /**
     * Return a metadata value from its key, or a default value if it doesn't exist.
     */
    public function metadata(string $key, mixed $default = null): mixed
    {
        return data_get($this->intent->metadata, $key, $default);
    }

    /**
     * Create a new redirect response to the previously intended location.
     */
    public function toIntended($default = '/', $status = 302, $headers = [], $secure = null): RedirectResponse
    {
        return $this->redirector->intended($default, $status, $headers, $secure);
    }
}
