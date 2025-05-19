<?php

namespace Laragear\EmailLogin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Traits\ForwardsCalls;
use Laragear\EmailLogin\EmailLoginBuilder;
use function array_keys;
use function back;

/**
 * @mixin \Laragear\EmailLogin\EmailLoginBuilder
 */
class EmailLoginRequest extends FormRequest
{
    use ForwardsCalls;

    /**
     * The email builder instance.
     */
    protected EmailLoginBuilder $builder;

    /**
     * Get the validation rules for this form request.
     */
    protected function validationRules(): array
    {
        return [
            'email' => ['required', 'email'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function validateResolved(): void
    {
        parent::validateResolved();

        $this->build();
    }

    /**
     * Returns the builder instance.
     */
    public function build(): EmailLoginBuilder
    {
        return $this->builder ??= $this->container->make(EmailLoginBuilder::class)
            ->withRemember($this->boolean('remember'))
            ->withCredentials(array_keys($this->validated()));
    }

    /**
     * Sends the Login Email and returns a redirection back to the form.
     */
    public function sendAndBack($status = 302, $headers = [], $fallback = false): RedirectResponse
    {
        $this->build()->send();

        return back($status, $headers, $fallback);
    }

    /**
     * Dynamically handle calls to the underlying builder, or the Form Request base instance.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters): mixed
    {
        return static::hasMacro($method)
            ? parent::__call($method, $parameters)
            : $this->forwardCallTo($this->build(), $method, $parameters);
    }
}
