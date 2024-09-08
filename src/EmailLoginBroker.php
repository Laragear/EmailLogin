<?php

namespace Laragear\EmailLogin;

use Closure;
use DateTimeInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Laragear\TokenAction\Builder;
use function array_pad;
use function explode;
use function implode;
use function value;


class EmailLoginBroker
{
    /**
     * The token generator for the email login intents.
     *
     * @var \Closure(\Laragear\EmailLogin\EmailLoginIntent):string
     */
    public static Closure $tokenGenerator;

    /**
     * Create a new Email Login Broker instance.
     */
    public function __construct(protected Builder $tokenBuilder, protected ?string $store, protected string $prefix)
    {
        //
    }

    /**
     * Set the store to use with the token builder.
     */
    public function store(?string $store): static
    {
        $this->store = $store;

        return $this;
    }

    /**
     * Register an email login intent in the cache store, and return the token.
     */
    public function create(
        string $guard,
        Authenticatable|string|int $id,
        DateTimeInterface|string|int $ttl,
        bool $remember = false,
        ?string $intended = null,
        array $metadata = [],
        Closure|string $token = null
    ): string {
        if ($id instanceof Authenticatable) {
            $id = $id->getAuthIdentifier();
        }

        $intent = new EmailLoginIntent($guard, $id, $remember, $intended, $metadata);

        $token = value($token ?? static::$tokenGenerator, $intent);

        $instance = $this->tokenBuilder->store($this->store)->as($this->getKey($token))->with($intent)->until($ttl);

        // Return the ID of the token created, after the prefix.
        return array_pad(explode('|', $instance->id, 2), 2, '')[1];
    }

    /**
     * Check if there is an email login intent in the cache store.
     */
    public function get(string $token): ?EmailLoginIntent
    {
        return $this->tokenBuilder->store($this->store)->find($this->getKey($token))?->payload;
    }

    /**
     * @param  string  $token
     * @return \Laragear\EmailLogin\EmailLoginIntent|null
     */
    public function pull(string $token): ?EmailLoginIntent
    {
        return $this->tokenBuilder->store($this->store)->consume($this->getKey($token))?->payload;
    }

    /**
     * Check if there is not an email login intent in the cache store.
     */
    public function missing(string $token): bool
    {
        return !$this->get($token);
    }

    /**
     * Build the cache key using the user email.
     */
    protected function getKey(string $token): string
    {
        return implode('|', [$this->prefix, $token]);
    }
}
