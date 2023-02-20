<?php
declare(strict_types=1);

namespace Revolution\Nostr\Client;

use Illuminate\Http\Client\Response;
use Revolution\Nostr\Client\Concerns\HasHttp;
use Revolution\Nostr\Event;
use Revolution\Nostr\Filter;

class PendingEvent
{
    use HasHttp;

    public function publish(Event|array $event, string $sk, string $relay): Response
    {
        return $this->http()->post('event/publish', [
            'event' => collect($event)->toArray(),
            'sk' => $sk,
            'relay' => $relay,
        ]);
    }

    /**
     * @param  array<Filter|array>  $filters
     */
    public function list(array $filters, string $relay): Response
    {
        return $this->http()->post('event/list', [
            'filters' => collect($filters)->toArray(),
            'relay' => $relay,
        ]);
    }

    public function get(Filter|array $filter, string $relay): Response
    {
        return $this->http()->post('event/get', [
            'filter' => collect($filter)->toArray(),
            'relay' => $relay,
        ]);
    }

    public function hash(Event|array $event): Response
    {
        return $this->http()->post('event/hash', [
            'event' => collect($event)->toArray(),
        ]);
    }

    public function sign(Event|array $event): Response
    {
        return $this->http()->post('event/sign', [
            'event' => collect($event)->toArray(),
        ]);
    }
}