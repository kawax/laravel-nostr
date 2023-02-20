<?php

declare(strict_types=1);

namespace Revolution\Nostr\Tag;

use Illuminate\Contracts\Support\Arrayable;

class EventTag implements Arrayable
{
    public function __construct(
        protected readonly string $id,
        protected readonly string $relay = '',
        protected readonly string $marker = '',
    ) {
    }

    public static function make(
        string $id,
        string $relay = '',
        string $marker = '',
    ): static {
        return new static(id: $id, relay: $relay, marker: $marker);
    }

    /**
     * @return array<string, string, string, string>
     */
    public function toArray(): array
    {
        return ['e', $this->id, $this->relay, $this->marker];
    }
}
