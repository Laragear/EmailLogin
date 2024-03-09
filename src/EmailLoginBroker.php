<?php

namespace Laragear\EmailLogin;

use DateInterval;
use DateTimeInterface;
use Illuminate\Contracts\Cache\Repository as CacheContract;
use function implode;


class EmailLoginBroker
{
    /**
     * Create a new Email Login Broker instance.
     */
    public function __construct(protected CacheContract $cache, protected string $prefix)
    {
        //
    }

    /**
     * Register an email login intent in the cache store.
     */
    public function register(string $guard, string|int $id, DateTimeInterface|DateInterval|int $ttl): void
    {
        $this->cache->put($this->getKey($guard, $id), true, $ttl);
    }

    /**
     * Check if there is an email login intent in the cache store.
     */
    public function retrieve(string $guard, string|int $id): bool
    {
        return $this->cache->pull($this->getKey($guard, $id), false);
    }

    /**
     * Check if there is not an email login intent in the cache store.
     */
    public function missing(string $guard, string|int $id): bool
    {
        return !$this->retrieve($guard, $id);
    }

    /**
     * Build the cache key using the user email.
     */
    protected function getKey(string $guard, string|int $id): string
    {
        return implode(':', [$this->prefix, $guard, $id]);
    }
}
