<?php

namespace Revolution\Nostr\Contracts;

use Revolution\Nostr\Contracts\Client\ClientEvent;
use Revolution\Nostr\Contracts\Client\ClientKey;
use Revolution\Nostr\Contracts\Client\ClientNip17;
use Revolution\Nostr\Contracts\Client\ClientNip19;
use Revolution\Nostr\Contracts\Client\ClientPool;

interface NostrDriver
{
    public function key(): ClientKey;

    public function event(): ClientEvent;

    public function pool(): ClientPool;

    public function relay();

    public function nip04();

    public function nip05();

    public function nip17(): ClientNip17;

    public function nip19(): ClientNip19;
}
