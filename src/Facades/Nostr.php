<?php

namespace Revolution\Nostr\Facades;

use Illuminate\Support\Facades\Facade;
use Revolution\Nostr\Client\NostrClient;
use Revolution\Nostr\Client\PendingEvent;
use Revolution\Nostr\Client\PendingKey;
use Revolution\Nostr\Client\PendingNip04;
use Revolution\Nostr\Client\PendingNip05;
use Revolution\Nostr\Client\PendingNip19;
use Revolution\Nostr\Client\PendingPool;
use Revolution\Nostr\Contracts\NostrFactory;
use Revolution\Nostr\NostrManager;

/**
 * Basic Nostr client.
 *
 * @method static static driver(string $driver)
 * @method static PendingKey key()
 * @method static PendingEvent event()
 * @method static PendingPool pool()
 * @method static PendingNip04 nip04()
 * @method static PendingNip05 nip05()
 * @method static PendingNip19 nip19()
 *
 * @see NostrClient
 * @mixin NostrManager
 */
class Nostr extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return NostrFactory::class;
    }
}
