<?php

declare(strict_types=1);

namespace Revolution\Nostr;

use BackedEnum;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Traits\Tappable;
use Mdanter\Ecc\Crypto\Signature\SchnorrSignature;
use Stringable;
use swentel\nostr\Key\Key;
use Throwable;

use function Illuminate\Support\enum_value;

class Event implements Jsonable, Arrayable, Stringable
{
    use Tappable;

    public readonly string $id;
    public readonly string $pubkey;
    public readonly string $sig;

    public function __construct(
        public readonly int|Kind $kind = Kind::Metadata,
        public readonly string $content = '',
        public int $created_at = 0,
        public readonly array $tags = [],
    ) {
        if ($this->created_at === 0) {
            $this->created_at = now()->timestamp;
        }
    }

    /**
     * Make new event.
     */
    public static function make(
        int|Kind $kind = Kind::Metadata,
        string $content = '',
        int $created_at = 0,
        array $tags = [],
    ): static {
        return new static(...func_get_args());
    }

    /**
     * From signed event.
     */
    public static function makeSigned(
        int|Kind $kind,
        string $content,
        int $created_at,
        array $tags,
        string $id,
        string $pubkey,
        string $sig,
    ): static {
        return static::make(
            kind: $kind,
            content: $content,
            created_at: $created_at,
            tags: $tags,
        )->withId(id: $id)->withPublicKey(pubkey: $pubkey)->withSign(sig: $sig);
    }

    /**
     * Make signed event from array.
     *
     * @param  array{
     *     kind: int|Kind,
     *     content: string,
     *     created_at: int,
     *     tags: array,
     *     id: int,
     *     pubkey: string,
     *     sig: string
     * }  $event
     */
    public static function fromArray(array $event): static
    {
        return static::makeSigned(...$event);
    }

    public function validate(): bool
    {
        return Validator::make(data: $this->toArray(), rules: [
            'kind' => 'required|filled|numeric|integer',
            'content' => 'string',
            'created_at' => 'required|filled|numeric|integer',
            'tags' => 'array',
            'id' => 'sometimes|required|filled|string|size:64',
            'pubkey' => 'sometimes|required|filled|string|size:64',
            'sig' => 'sometimes|required|filled|string|size:128',
        ])->passes();
    }

    public function withId(string $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function withPublicKey(string $pubkey): static
    {
        $this->pubkey = $pubkey;

        return $this;
    }

    public function withSign(string $sig): static
    {
        $this->sig = $sig;

        return $this;
    }

    public function isUnsigned(): bool
    {
        return empty($this->sig);
    }

    public function isSigned(): bool
    {
        return ! $this->isUnsigned();
    }

    public function sign(string $sk): static
    {
        if ($this->isSigned()) {
            return $this;
        }

        if (empty($this->pubkey)) {
            $key = new Key();
            $this->pubkey = $key->getPublicKey($sk);
        }

        if (empty($this->id)) {
            $this->id = $this->hash();
        }

        $this->sig = data_get((new SchnorrSignature())->sign($sk, $this->id), 'signature', '');

        return $this;
    }

    /**
     * @return string Hash for event.id
     *
     * @throws Throwable
     */
    public function hash(): string
    {
        throw_unless(isset($this->pubkey));

        $json = json_encode([
            0,
            $this->pubkey,
            $this->created_at,
            $this->kind,
            collect($this->tags)->toArray(),
            $this->content,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return hash(algo: 'sha256', data: $json);
    }

    public function rootId(): ?string
    {
        $root = collect($this->tags)
            ->first(fn (array $tag) => head($tag) === 'e' && last($tag) === 'root');

        return $root[1] ?? null;
    }

    public function replyId(): ?string
    {
        $root = collect($this->tags)
            ->first(fn (array $tag) => head($tag) === 'e' && last($tag) === 'reply');

        return $root[1] ?? null;
    }

    /**
     * @return array{
     *     kind: int|Kind,
     *     content: string,
     *     created_at: int,
     *     tags: array,
     *     id?: int,
     *     pubkey?: string,
     *     sig?: string
     * }
     */
    public function toArray(): array
    {
        return collect(get_object_vars($this))
            ->reject(fn ($item) => is_null($item))
            ->map(fn ($item) => $item instanceof BackedEnum ? $item->value : $item)
            ->map($this->castTags(...))
            ->toArray();
    }

    protected function castTags(mixed $item, string $key): mixed
    {
        if ($key === 'tags' && is_array($item)) {
            $item = collect($item)
                ->map(fn ($tag) => $tag instanceof Arrayable ? $tag->toArray() : $tag)
                ->toArray();
        }

        return $item;
    }

    public function toJson($options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    public function __toString(): string
    {
        return $this->toJson();
    }
}
