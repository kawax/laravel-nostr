<?php

declare(strict_types=1);

namespace Revolution\Nostr\Tags;

use Illuminate\Contracts\Support\Arrayable;

/**
 * NIP-1.
 */
class PersonTag implements Arrayable
{
    /**
     * @param  string  $p  pubkey
     */
    public function __construct(
        protected readonly string $p,
        protected readonly string $relay = '',
        protected readonly string $petname = '',
    ) {}

    /**
     * @param  string  $p  pubkey
     */
    public static function make(
        string $p,
        string $relay = '',
        string $petname = '',
    ): static {
        return new static(...func_get_args());
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: string}
     */
    public function toArray(): array
    {
        return ['p', $this->p, $this->relay, $this->petname];
    }
}
