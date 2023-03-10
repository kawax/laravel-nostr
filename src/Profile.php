<?php

declare(strict_types=1);

namespace Revolution\Nostr;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Stringable;

class Profile implements Jsonable, Arrayable, Stringable
{
    public function __construct(
        public string $name = '',
        public string $display_name = '',
        public string $about = '',
        public string $picture = '',
        public string $banner = '',
        public string $website = '',
        public string $nip05 = '',
        public string $lud06 = '',
        public string $lud16 = '',
    ) {
    }

    public static function fromArray(array $profile): static
    {
        return new static(
            name: $profile['name'] ?? '',
            display_name: $profile['display_name'] ?? '',
            about: $profile['about'] ?? '',
            picture: $profile['picture'] ?? '',
            banner: $profile['banner'] ?? '',
            website: $profile['website'] ?? '',
            nip05: $profile['nip05'] ?? '',
            lud06: $profile['lud06'] ?? '',
            lud16: $profile['lud16'] ?? '',
        );
    }

    public static function fromJson(string $profile): static
    {
        return static::fromArray(json_decode($profile, true));
    }

    public function toArray(): array
    {
        return get_object_vars($this);
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
