<?php

namespace Laragear\EmailLogin\Http\Requests;

use Closure;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;
use Laragear\EmailLogin\EmailLoginBroker;
use Laragear\EmailLogin\EmailLoginIntent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use function __;
use function data_get;

class LoginByEmailRequest extends FormRequest
{
    /**
     * The name of the request query key where the login token should be.
     */
    public const INTENT_KEY = 'login-intent-key';

    /**
     * The name of the request query key where the guard name should be.
     */
    public const STORE_KEY = 'login-intent-store';

    /**
     * If the session should be destroyed on regeneration
     */
    public static bool $destroyOnRegeneration = false;

    /**
     * The Token Action of this Login Email request.
     */
    protected ?EmailLoginIntent $intent;

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            static::STORE_KEY => ['required', 'string'],
            static::INTENT_KEY => [
                'required', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                    $this->intent = $this->container
                        ->make(EmailLoginBroker::class)
                        ->store($this->query(static::STORE_KEY))
                        ->get((string) $value);

                    if (!$this->intent) {
                        $fail();
                    }
                }
            ],
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator): never
    {
        // Abort if we're showing a view through a `get` method.
        if ($this->isMethod('get')) {
            throw new HttpException(419, __('Page Expired'));
        }

        parent::failedValidation($validator);
    }

    /**
     * Handle a passed validation attempt.
     */
    protected function passedValidation(): void
    {
        // Only log in the user if this is a form submission.
        if ($this->isMethod('post')) {
            $this->login(
                $this->container->make('auth')->guard($this->intent->guard),
                $this->intent->id,
                $this->intent->remember
            );

            $this->session()->regenerate(static::$destroyOnRegeneration);

            // Add the intended URL to the session store.
            $this->container->make('session.store')->set('url.intended', $this->intent->intended);
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
    public function metadata(string $key, mixed $default): mixed
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
