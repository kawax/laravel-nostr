<?php

namespace Revolution\Nostr\Facades;

use Illuminate\Support\Facades\Facade;
use Revolution\Nostr\Client\NostrClient;
use Revolution\Nostr\Client\PendingEvent;
use Revolution\Nostr\Client\PendingKey;
use Revolution\Nostr\Client\PendingNip05;

/**
 * @method static PendingKey key()
 * @method static PendingEvent event()
 * @method static PendingNip05 nip05()
 *
 * @see NostrClient
 */
class Nostr extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return NostrClient::class;
    }
}