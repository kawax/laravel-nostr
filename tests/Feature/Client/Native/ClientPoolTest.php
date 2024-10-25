<?php

declare(strict_types=1);

namespace Tests\Feature\Client\Native;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Revolution\Nostr\Client\Native\DummyWebSocket;
use Revolution\Nostr\Client\Native\NativePool;
use Revolution\Nostr\Event;
use Revolution\Nostr\Facades\Nostr;
use Revolution\Nostr\Filter;
use Revolution\Nostr\Kind;
use swentel\nostr\Sign\Sign;
use Tests\TestCase;

class ClientPoolTest extends TestCase
{
    public function test_pool_event_publish()
    {
        $this->mock(DummyWebSocket::class, function (MockInterface $mock) {
            $mock->shouldReceive('publish')->once()->andReturn([
                'wss://1' => new Response(Http::response([])->wait()),
                'wss://2' => new Response(Http::response([])->wait()),
            ]);
        });

        $this->mock(Sign::class, function (MockInterface $mock) {
            $mock->shouldReceive('signEvent')->once();
        });

        $event = new Event(kind: Kind::Text);

        $responses = (new NativePool())
            ->publish(event: $event, sk: '', relays: ['wss://1', 'wss://2']);

        $this->assertTrue($responses['wss://1']->ok());
        $this->assertTrue($responses['wss://2']->ok());
    }

    public function test_pool_event_list()
    {
        $this->mock(DummyWebSocket::class, function (MockInterface $mock) {
            $mock->shouldReceive('request')->once()->andReturn([
                'wss://1' => new Response(Http::response([])->wait()),
                'wss://2' => new Response(Http::response([])->wait()),
            ]);
        });

        $filter = new Filter(authors: []);

        $responses = (new NativePool())
            ->withRelays(relays: ['wss://1', 'wss://2'])
            ->list(filter: $filter);

        $this->assertTrue($responses['wss://1']->ok());
        $this->assertTrue($responses['wss://2']->ok());
    }

    public function test_pool_event_list_real()
    {
        $filter = new Filter(limit: 2);

        $responses = Nostr::driver('native')
            ->pool()
            ->list(
                filter: $filter,
                relays: Arr::take(Config::get('nostr.relays'), limit: 2),
            );

        $this->assertIsArray(head($responses)?->json());
        $this->assertCount(2, head($responses)?->json('events'));
        $this->assertCount(2, $responses);
    }

    public function test_pool_event_get()
    {
        $this->mock(DummyWebSocket::class, function (MockInterface $mock) {
            $mock->shouldReceive('request')->once()->andReturn([
                '1' => new Response(Http::response([])->wait()),
                '2' => new Response(Http::response([])->wait()),
            ]);
        });

        $filter = new Filter(authors: []);

        $responses = (new NativePool())
            ->withRelays(relays: ['wss://1', 'wss://2'])
            ->get(filter: $filter, relays: ['1', '2']);

        $this->assertTrue($responses['1']->ok());
        $this->assertTrue($responses['2']->ok());
    }

    public function test_pool_event_get_real()
    {
        $filter = new Filter(limit: 1);

        $responses = Nostr::driver('native')
            ->pool()
            ->get(
                filter: $filter,
                relays: Arr::take(Config::get('nostr.relays'), limit: 2),
            );

        $this->assertIsArray(head($responses)?->json());
        $this->assertArrayHasKey('event', head($responses)?->json());
        $this->assertCount(2, $responses);
    }
}