<?php

namespace Laragear\EmailLogin;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;
use Stringable;
use function json_encode;

class EmailLoginIntent implements JsonSerializable, Arrayable, Jsonable, Stringable
{
    public function __construct(
        readonly public string $guard,
        readonly public mixed $id,
        readonly public bool $remember,
        readonly public ?string $intended,
        readonly public array $metadata
    ) {
        //
    }

    /**
     * Return an array representation of the object.
     */
    public function __serialize(): array
    {
        return $this->toArray();
    }

    /**
     * Fill the object data from its unserialized array representation.
     */
    public function __unserialize(array $data): void
    {
        $this->guard = $data['guard'];
        $this->id = $data['id'];
        $this->remember = $data['remember'];
        $this->intended = $data['intended'];
        $this->metadata = $data['metadata'];
    }

    /**
     * @inheritDoc
     */
    public function toArray()
    {
        return [
            'guard' => $this->guard,
            'id' => $this->id,
            'remember' => $this->remember,
            'intended' => $this->intended,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * @inheritDoc
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        return $this->toJson();
    }
}
