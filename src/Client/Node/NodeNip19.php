<?php

declare(strict_types=1);

namespace Revolution\Nostr\Client\Node;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Macroable;
use Revolution\Nostr\Client\Node\Concerns\HasHttp;
use Revolution\Nostr\Contracts\Client\ClientNip19;
use Revolution\Nostr\Nip19\AddressPointer;
use Revolution\Nostr\Nip19\EventPointer;
use Revolution\Nostr\Nip19\ProfilePointer;

class NodeNip19 implements ClientNip19
{
    use Conditionable;
    use HasHttp;
    use Macroable;

    /**
     * Decode NIP-19 string.
     *
     * @param  string  $n  nsec, npub, note, nprofile, nevent, naddr
     */
    public function decode(string $n): Response
    {
        return $this->http()->post('nip19/decode', [
            'n' => $n,
        ]);
    }

    /**
     * encode note id.
     */
    public function note(string $id): Response
    {
        return $this->http()->post('nip19/note', [
            'note' => $id,
        ]);
    }

    /**
     * encode profile.
     */
    public function nprofile(ProfilePointer $profile): Response
    {
        return $this->http()->post('nip19/nprofile', [
            'profile' => $profile->toArray(),
        ]);
    }

    /**
     * encode event.
     */
    public function nevent(EventPointer $event): Response
    {
        return $this->http()->post('nip19/nevent', [
            'event' => $event->toArray(),
        ]);
    }

    /**
     * encode addr.
     */
    public function naddr(AddressPointer $addr): Response
    {
        return $this->http()->post('nip19/naddr', [
            'addr' => $addr->toArray(),
        ]);
    }
}
