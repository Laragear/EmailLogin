<?php

namespace Tests;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;
use Laragear\EmailLogin\EmailLoginBroker;
use Laragear\EmailLogin\EmailLoginIntent;
use Laragear\TokenAction\Builder;
use Laragear\TokenAction\Store;
use Laragear\TokenAction\Token;
use Mockery;

class EmailLoginBrokerTest extends TestCase
{
    protected static Closure $original;
    protected \Mockery\MockInterface|Builder $tokenBuilder;
    protected EmailLoginBroker $broker;

    public function setUp(): void
    {
        $this->afterApplicationCreated(function (): void {
            static::$original = EmailLoginBroker::$tokenGenerator;

            $this->tokenBuilder = $this->mock(Builder::class);
            $this->broker = $this->app->make(EmailLoginBroker::class);
        });

        $this->beforeApplicationDestroyed(static function (): void {
            EmailLoginBroker::$tokenGenerator = static::$original;
        });

        parent::setUp();
    }

    public function test_uses_custom_token_generator(): void
    {
        EmailLoginBroker::$tokenGenerator = fn() => 'random';

        $this->tokenBuilder->expects('store')->with(null)->andReturnSelf();
        $this->tokenBuilder->expects('as')->withArgs(function (string $token): bool {
            return $token === 'email-login|random';
        })->andReturnSelf();
        $this->tokenBuilder->expects('with')->with(Mockery::type(EmailLoginIntent::class))->andReturnSelf();
        $this->tokenBuilder->expects('until')->with(30)->andReturn(
            new Token($this->mock(Store::class), new CarbonImmutable('now'), 'str')
        );

        $this->broker->create('guard', '1', 30);
    }

    public function test_uses_config_token_prefix(): void
    {
        $this->app->make('config')->set('email-login.cache.prefix', 'test_prefix');

        $this->tokenBuilder->expects('store')->with(null)->andReturnSelf();
        $this->tokenBuilder->expects('as')->withArgs(function (string $token): bool {
            return Str::startsWith($token, 'test_prefix|');
        })->andReturnSelf();
        $this->tokenBuilder->expects('with')->with(Mockery::type(EmailLoginIntent::class))->andReturnSelf();
        $this->tokenBuilder->expects('until')->with(30)->andReturn(
            new Token($this->mock(Store::class), new CarbonImmutable('now'), 'str')
        );

        $this->app->forgetInstance(EmailLoginBroker::class);

        $this->app->make(EmailLoginBroker::class)->create('guard', '1', 30);
    }

    public function test_uses_config_cache_store(): void
    {
        $this->app->make('config')->set('email-login.cache.store', 'test_store');

        $this->tokenBuilder->expects('store')->with('test_store')->andReturnSelf();
        $this->tokenBuilder->expects('as')->withArgs(function (string $token): bool {
            return Str::startsWith($token, 'email-login|');
        })->andReturnSelf();
        $this->tokenBuilder->expects('with')->with(Mockery::type(EmailLoginIntent::class))->andReturnSelf();
        $this->tokenBuilder->expects('until')->with(30)->andReturn(
            new Token($this->mock(Store::class), new CarbonImmutable('now'), 'str')
        );

        $this->app->forgetInstance(EmailLoginBroker::class);

        $this->app->make(EmailLoginBroker::class)->create('guard', '1', 30);
    }

    public function test_creates_with_remember(): void
    {
        $this->tokenBuilder->expects('store')->andReturnSelf();
        $this->tokenBuilder->expects('as')->andReturnSelf();
        $this->tokenBuilder->expects('with')->withArgs(function (EmailLoginIntent $intent): bool {
            return $intent->remember;
        })->andReturnSelf();
        $this->tokenBuilder->expects('until')->andReturn(
            new Token($this->mock(Store::class), new CarbonImmutable('now'), 'email-login|test-token')
        );

        $this->broker->create('guard', '1', 30, remember: true);
    }

    public function test_creates_with_intended(): void
    {
        $this->tokenBuilder->expects('store')->andReturnSelf();
        $this->tokenBuilder->expects('as')->andReturnSelf();
        $this->tokenBuilder->expects('with')->withArgs(function (EmailLoginIntent $intent): bool {
            return $intent->intended === 'test-intended';
        })->andReturnSelf();
        $this->tokenBuilder->expects('until')->andReturn(
            new Token($this->mock(Store::class), new CarbonImmutable('now'), 'email-login|test-token')
        );

        $this->broker->create('guard', '1', 30, intended: 'test-intended');
    }

    public function test_creates_with_metadata(): void
    {
        $this->tokenBuilder->expects('store')->andReturnSelf();
        $this->tokenBuilder->expects('as')->andReturnSelf();
        $this->tokenBuilder->expects('with')->withArgs(function (EmailLoginIntent $intent): bool {
            return $intent->metadata === ['foo' => 'bar'];
        })->andReturnSelf();
        $this->tokenBuilder->expects('until')->andReturn(
            new Token($this->mock(Store::class), new CarbonImmutable('now'), 'email-login|test-token')
        );

        $this->broker->create('guard', '1', 30, metadata: ['foo' => 'bar']);
    }

    public function test_creates_with_token(): void
    {
        $this->tokenBuilder->expects('store')->with(null)->andReturnSelf();
        $this->tokenBuilder->expects('as')->withArgs(function (string $token): bool {
            return $token === 'email-login|test-token';
        })->andReturnSelf();
        $this->tokenBuilder->expects('with')->with(Mockery::type(EmailLoginIntent::class))->andReturnSelf();
        $this->tokenBuilder->expects('until')->with(30)->andReturn(
            new Token($this->mock(Store::class), new CarbonImmutable('now'), 'email-login|test-token')
        );

        $token = $this->broker->create('guard', '1', 30, token: 'test-token');

        static::assertSame('test-token', $token);
    }

    public function test_get_returns_null_when_not_found(): void
    {
        $this->tokenBuilder->expects('store')->with(null)->andReturnSelf();
        $this->tokenBuilder->expects('find')->with('email-login|test-token')->andReturnNull();

        static::assertNull($this->broker->get('test-token'));
    }

    public function test_get_returns_payload_when_found(): void
    {
        $intent = new EmailLoginIntent('foo', 'bar', true, '/', []);

        $this->tokenBuilder->expects('store')->with(null)->andReturnSelf();
        $this->tokenBuilder->expects('find')->with('email-login|test-token')->andReturn(
            new Token(Mockery::mock(Store::class), new CarbonImmutable('now'), 'foo', 1, $intent)
        );

        static::assertSame($intent, $this->broker->get('test-token'));
    }

    public function test_pulls_returns_null_when_not_found(): void
    {
        $this->tokenBuilder->expects('store')->with(null)->andReturnSelf();
        $this->tokenBuilder->expects('consume')->with('email-login|test-token')->andReturnNull();

        static::assertNull($this->broker->pull('test-token'));
    }

    public function test_pulls_returns_payload_when_found(): void
    {
        $intent = new EmailLoginIntent('foo', 'bar', true, '/', []);

        $this->tokenBuilder->expects('store')->with(null)->andReturnSelf();
        $this->tokenBuilder->expects('consume')->with('email-login|test-token')->andReturn(
            new Token(Mockery::mock(Store::class), new CarbonImmutable('now'), 'foo', 1, $intent)
        );

        static::assertSame($intent, $this->broker->pull('test-token'));
    }

    public function test_missing_returns_false_when_not_found(): void
    {
        $this->tokenBuilder->expects('store')->with(null)->andReturnSelf();
        $this->tokenBuilder->expects('find')->with('email-login|test-token')->andReturnNull();

        static::assertTrue($this->broker->missing('test-token'));
    }

    public function test_missing_returns_true_when_found(): void
    {
        $this->tokenBuilder->expects('store')->with(null)->andReturnSelf();
        $this->tokenBuilder->expects('find')->with('email-login|test-token')->andReturn(
            new Token(Mockery::mock(Store::class), new CarbonImmutable('now'), 'foo', 1,
                new EmailLoginIntent('foo', 'bar', true, '/', []))
        );

        static::assertFalse($this->broker->missing('test-token'));
    }

    public function test_creates_with_authenticatable(): void
    {
        $this->tokenBuilder->expects('store')->with(null)->andReturnSelf();
        $this->tokenBuilder->expects('as')->andReturnSelf();
        $this->tokenBuilder->expects('with')->withArgs(fn($intent) => $intent->id === 'test-id')->andReturnSelf();
        $this->tokenBuilder->expects('until')->with(30)->andReturn(
            new Token($this->mock(Store::class), new CarbonImmutable('now'), 'email-login|test-token')
        );

        static::assertSame('test-token', $this->broker->create('web', new TestAuthenticatable(), 30));
    }
}

class TestAuthenticatable implements Authenticatable
{
    /**
     * @inheritDoc
     */
    public function getAuthIdentifierName()
    {
        //
    }

    /**
     * @inheritDoc
     */
    public function getAuthIdentifier()
    {
        return 'test-id';
    }

    /**
     * @inheritDoc
     */
    public function getAuthPasswordName()
    {
        //
    }

    /**
     * @inheritDoc
     */
    public function getAuthPassword()
    {
        //
    }

    /**
     * @inheritDoc
     */
    public function getRememberToken()
    {
        //
    }

    /**
     * @inheritDoc
     */
    public function setRememberToken($value)
    {
        //
    }

    /**
     * @inheritDoc
     */
    public function getRememberTokenName()
    {
        //
    }
}
