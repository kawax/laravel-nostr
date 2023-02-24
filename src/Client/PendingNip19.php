<?php

declare(strict_types=1);

namespace Revolution\Nostr\Client;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Traits\Macroable;
use Revolution\Nostr\Client\Concerns\HasHttp;

class PendingNip19
{
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
    public function nprofile(array $profile): Response
    {
        return $this->http()->post('nip19/nprofile', [
            'profile' => $profile,
        ]);
    }

    /**
     * encode event.
     */
    public function nevent(array $event): Response
    {
        return $this->http()->post('nip19/nevent', [
            'event' => $event,
        ]);
    }

    /**
     * encode addr.
     */
    public function naddr(array $addr): Response
    {
        return $this->http()->post('nip19/naddr', [
            'addr' => $addr,
        ]);
    }
}
