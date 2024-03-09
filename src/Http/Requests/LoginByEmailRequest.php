<?php

namespace Laragear\EmailLogin\Http\Requests;

use Illuminate\Auth\Events\Failed;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laragear\EmailLogin\EmailLoginBroker;
use function array_keys;

class LoginByEmailRequest extends FormRequest
{
    /**
     * If the session should be destroyed on regeneration
     */
    public static bool $destroyOnRegeneration = false;

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'id' => ['required'],
            'guard' => ['required', Rule::in(array_keys($this->container->make('config')->get('auth.guards')))],
            'remember' => ['sometimes', 'boolean'],
            'intended' => ['sometimes'],
        ];
    }

    /**
     * Handle a passed validation attempt.
     */
    protected function passedValidation(): void
    {
        if ($this->missingIntent() || !$this->login($this->guard())) {
            $this->bailOut();
        }

        $this->session()->regenerate(static::$destroyOnRegeneration);

        if ($intended = $this->query('intended')) {
            $this->session()->put('url.intended', $intended);
        }
    }

    /**
     * Check if the Email Login intent is missing.
     */
    protected function missingIntent(): bool
    {
        return $this->container->make(EmailLoginBroker::class)->missing($this->query('guard'), $this->query('id'));
    }

    /**
     * Log in the user, returning the authenticatable instance.
     */
    protected function login(StatefulGuard $guard): Authenticatable|false
    {
        return $guard->loginUsingId($this->query('id'), $this->boolean('remember'));
    }

    /**
     * Return the authentication guard for this Login by Email request.
     */
    protected function guard(): StatefulGuard
    {
        return $this->container->make('auth')->guard($this->query('guard'));
    }

    /**
     * Gracefully bail out of the login procedure.
     */
    protected function bailOut(): never
    {
        $this->container->make('events')->dispatch(
            new Failed($this->query('guard'), null, ['id' => $this->query('id')])
        );

        throw ValidationException::withMessages(['id' => '']);
    }

    /**
     * Redirects the user to a new location.
     */
    public function redirect(string $location = null): Redirector|RedirectResponse
    {
        return $location ? $this->redirector->to($location) : $this->redirector;
    }
}
