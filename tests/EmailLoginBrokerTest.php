<?php

namespace Tests;

use Illuminate\Contracts\Cache\Repository as CacheContract;
use Laragear\EmailLogin\EmailLoginBroker;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;

class EmailLoginBrokerTest extends TestCase
{
    protected MockInterface $cache;
    protected EmailLoginBroker $broker;

    public function setUp(): void
    {
        $this->afterApplicationCreated(function () {
            $this->cache = Mockery::mock(CacheContract::class);
            $this->broker = new EmailLoginBroker($this->cache, 'test_prefix');
        });

        parent::setUp();
    }

    #[Test]
    public function registers_email_intent(): void
    {
        $this->cache->expects('put')->with('test_prefix:test_guard:1', 1, 60);

        $this->broker->register('test_guard', 1, 60);
    }

    #[Test]
    public function retrieves_existing_email_login_intent(): void
    {
        $this->cache->expects('pull')->with('test_prefix:test_guard:1', false)->andReturnTrue();

        static::assertTrue($this->broker->retrieve('test_guard', '1'));
    }

    #[Test]
    public function retrieves_non_existing_email_login_intent_as_false(): void
    {
        $this->cache->expects('pull')->twice()->with('test_prefix:test_guard:1', false)->andReturnFalse();

        static::assertFalse($this->broker->retrieve('test_guard', '1'));
        static::assertTrue($this->broker->missing('test_guard', '1'));
    }
}
