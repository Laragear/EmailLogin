<?php

namespace Laragear\EmailLogin;

use Carbon\CarbonImmutable;
use Closure;
use DateInterval;
use DateTimeInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Cache\Repository as CacheContract;
use Laragear\TokenAction\Builder;
use Laragear\TokenAction\Store;
use Laragear\TokenAction\Token;
use function implode;


class EmailLoginBroker
{
    /**
     * Create a new Email Login Broker instance.
     */
    public function __construct(
        protected Builder $tokenBuilder,
        protected ?string $store,
        protected string $prefix,
        protected Closure|string|null $token = null
    ) {
        //
    }

    /**
     * Sets the token name to persist in the cache store.
     *
     * @param  (\Closure(\Laragear\EmailLogin\EmailLoginIntent):string)|string  $token
     * @return $this
     */
    public function token(Closure|string $token): static
    {
        $this->token = $token;

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
        string $intended = '/',
        array $metadata = []
    ): string {
        if ($id instanceof Authenticatable) {
            $id = $id->getAuthIdentifier();
        }

        return $this->tokenBuilder->store($this->store)
            ->when($this->token)->as($this->token)
            ->with(new EmailLoginIntent($guard, $id, $remember, $intended, $metadata))
            ->until($ttl)->id;
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
