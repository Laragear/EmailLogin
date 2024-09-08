<?php

namespace Tests;

use Laragear\EmailLogin\EmailLoginIntent;
use PHPUnit\Framework\TestCase as BaseTestCase;
use function json_encode;
use function serialize;
use function unserialize;

class EmailLoginIntentTest extends BaseTestCase
{
    protected EmailLoginIntent $intent;

    protected function setUp(): void
    {
        $this->intent = new EmailLoginIntent('foo', 'bar', true, 'baz', ['foo' => 'bar']);
    }

    public function test_to_array(): void
    {
        static::assertSame(
            [
                'guard' => 'foo',
                'id' => 'bar',
                'remember' => true,
                'intended' => 'baz',
                'metadata' => ['foo' => 'bar'],
            ],
            $this->intent->toArray()
        );
    }

    public function test_serializes(): void
    {
        static::assertSame(
            [
                'guard' => 'foo',
                'id' => 'bar',
                'remember' => true,
                'intended' => 'baz',
                'metadata' => ['foo' => 'bar'],
            ],
            unserialize(serialize($this->intent))->toArray()
        );
    }

    public function test_to_json(): void
    {
        static::assertSame(
            '{"guard":"foo","id":"bar","remember":true,"intended":"baz","metadata":{"foo":"bar"}}',
            json_encode($this->intent)
        );
    }

    public function test_to_string(): void
    {
        static::assertSame(
            '{"guard":"foo","id":"bar","remember":true,"intended":"baz","metadata":{"foo":"bar"}}',
            (string) $this->intent
        );
    }
}
