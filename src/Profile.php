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
    ) {
    }

    public static function fromJson(string $json): static
    {
        $arr = json_decode($json, true);

        return new static(
            name: $arr['name'] ?? '',
            display_name: $arr['display_name'] ?? '',
            about: $arr['about'] ?? '',
            picture: $arr['picture'] ?? '',
            banner: $arr['banner'] ?? '',
            website: $arr['website'] ?? '',
            nip05: $arr['nip05'] ?? '',
            lud06: $arr['lud06'] ?? '',
        );
    }

    public static function fromArray(array $arr): static
    {
        return new static(
            name: $arr['name'] ?? '',
            display_name: $arr['display_name'] ?? '',
            about: $arr['about'] ?? '',
            picture: $arr['picture'] ?? '',
            banner: $arr['banner'] ?? '',
            website: $arr['website'] ?? '',
            nip05: $arr['nip05'] ?? '',
            lud06: $arr['lud06'] ?? '',
        );
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
