<?php

declare(strict_types=1);

namespace Tests\Feature\Social;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Revolution\Nostr\Event;
use Revolution\Nostr\Exceptions\EventNotFoundException;
use Revolution\Nostr\Facades\Nostr;
use Revolution\Nostr\Facades\Social;
use Revolution\Nostr\Kind;
use Revolution\Nostr\Profile;
use Revolution\Nostr\Social\SocialClient;
use Revolution\Nostr\Tags\PersonTag;
use Tests\TestCase;

class SocilalTest extends TestCase
{
    protected SocialClient $social;

    public function setUp(): void
    {
        parent::setUp();

        $this->social = new SocialClient();
    }

    public function test_facade()
    {
        $social = Social::withRelay(relay: 'wss://')->withKey(sk: 'sk', pk: 'pk');

        $this->assertInstanceOf(SocialClient::class, $social);
    }

    public function test_create_new_user()
    {
        Nostr::shouldReceive('key->generate->collect')->once()->andReturn(collect([
            'sk' => 'sk',
            'pk' => 'pk',
        ]));

        Nostr::shouldReceive('event->publish->throw')->once();

        Nostr::shouldReceive('event->publish->throw')->once();

        $p = new Profile(name: 'name');

        $response = $this->social->createNewUser($p);

        $this->assertArrayHasKey('keys', $response);
        $this->assertArrayHasKey('profile', $response);
    }

    public function test_create_new_user_fail()
    {
        $this->expectException(\Exception::class);

        Nostr::shouldReceive('key->generate->collect')->once()->andReturn(collect());

        Nostr::shouldReceive('event->publish->throw')->never();

        $p = new Profile(name: 'name');

        $response = $this->social->withRelay('wss://')->createNewUser($p);

        $this->assertArrayNotHasKey('keys', $response);
        $this->assertArrayNotHasKey('profile', $response);
    }

    public function test_profile()
    {
        Nostr::shouldReceive('event->get->json')->once()->andReturn(['name' => 'name']);

        $response = $this->social->profile(pk: 'pk');

        $this->assertIsArray($response);
    }

    public function test_follows()
    {
        Nostr::shouldReceive('event->get->collect')->once()->andReturn(collect([
            ['p', '1'],
            ['p', '2'],
        ]));

        $follows = $this->social->withKey('sk', 'pk')->follows();

        $this->assertSame(['1', '2'], $follows);
    }

    public function test_update_follows()
    {
        Nostr::shouldReceive('event->publish->successful')->once()->andReturnTrue();

        $follows = [
            new PersonTag(p: '1'),
            new PersonTag(p: '2'),
        ];

        $res = $this->social->withKey('sk', 'pk')
                            ->updateFollows(follows: $follows);

        $this->assertTrue($res->successful());
    }

    public function test_relays()
    {
        Nostr::shouldReceive('event->get->json')->once()->andReturn([
            ['r', 'wss://1'],
            ['r', 'wss://2'],
        ]);

        $follows = $this->social->withKey('sk', 'pk')->relays();

        $this->assertSame([['r', 'wss://1'], ['r', 'wss://2']], $follows);
    }

    public function test_update_relays()
    {
        Nostr::shouldReceive('event->publish->successful')->once()->andReturnTrue();

        $relays = [
            'wss://1',
            'wss://2',
        ];

        $res = $this->social->withKey('sk', 'pk')
                            ->updateRelays(relays: $relays);

        $this->assertTrue($res->successful());
    }

    public function test_profiles()
    {
        Nostr::shouldReceive('event->list->json')->once()->andReturn([
            ['name' => '1'],
            ['name' => '2'],
        ]);

        $profiles = $this->social->profiles(['1', '2']);

        $this->assertIsArray($profiles);
    }

    public function test_notes()
    {
        Nostr::shouldReceive('event->list->collect->sortByDesc->toArray')->once()->andReturn([
            ['id' => '1'],
            ['id' => '2'],
        ]);

        $notes = $this->social->notes(authors: ['1', '2'], kinds: [1], since: 0, until: 0, limit: 10);

        $this->assertIsArray($notes);
    }

    public function test_merge()
    {
        $notes = [
            [
                'pubkey' => '1',
                'content' => '1',
            ],
            [
                'pubkey' => '2',
                'content' => '2',
            ],
            [
                'content' => '3',
            ],
        ];

        $profiles = [
            [
                'pubkey' => '1',
                'content' => '{"name": "test"}',
            ],
            [
                'pubkey' => '2',
            ],
        ];

        $notes = $this->social->mergeNotesAndProfiles(notes: $notes, profiles: $profiles);

        $this->assertSame([
            [
                'pubkey' => '1',
                'content' => '1',
                'name' => 'test',
            ],
            [
                'pubkey' => '2',
                'content' => '2',
            ],
        ], $notes);
    }

    public function test_timeline()
    {
        //follows
        Nostr::shouldReceive('event->get->collect')->once()->andReturn(collect([
            ['p', '1'],
            ['p', '2'],
        ]));

        //profiles
        Nostr::shouldReceive('event->list->json')->once()->andReturn([
            ['name' => '1', 'pubkey' => '1'],
            ['name' => '2', 'pubkey' => '2'],
        ]);

        //notes
        Nostr::shouldReceive('event->list->collect->sortByDesc->toArray')->once()->andReturn([
            ['id' => '1', 'pubkey' => '1'],
            ['id' => '2', 'pubkey' => '2'],
        ]);

        $notes = $this->social->withKey('sk', 'pk')
                              ->timeline(since: 0, until: 0, limit: 20);

        $this->assertIsArray($notes);
    }

    public function test_create_note()
    {
        Nostr::shouldReceive('event->publish->successful')->once()->andReturnTrue();

        $response = $this->social->withKey('sk', 'pk')
                                 ->createNote(content: 'test', tags: []);

        $this->assertTrue($response->successful());
    }

    public function test_create_note_to()
    {
        Nostr::shouldReceive('event->publish->successful')->once()->andReturnTrue();

        $response = $this->social->withKey('sk', 'pk')
                                 ->createNoteTo(content: 'test', pk: 'to');

        $this->assertTrue($response->successful());
    }

    public function test_create_note_hashtag()
    {
        Nostr::shouldReceive('event->publish->successful')->once()->andReturnTrue();

        $response = $this->social->withKey('sk', 'pk')
                                 ->createNoteWithHashTag(content: 'test', hashtags: ['test']);

        $this->assertTrue($response->successful());
    }

    public function test_reply()
    {
        Nostr::shouldReceive('event->publish->successful')->once()->andReturnTrue();

        $event = Event::makeSigned(
            kind: Kind::Text,
            content: 'test',
            created_at: 1,
            tags: [],
            id: '1',
            pubkey: '1',
            sig: '1',
        );

        $response = $this->social->withKey('sk', 'pk')
                                 ->reply(
                                     event: $event,
                                     content: 'test',
                                     mentions: ['1'],
                                     hashtags: ['test'],
                                 );

        $this->assertTrue($response->successful());
    }

    public function test_reply_root()
    {
        Nostr::shouldReceive('event->publish->successful')->once()->andReturnTrue();

        $event = Event::makeSigned(
            kind: Kind::Text,
            content: 'test',
            created_at: 1,
            tags: [['e', '1', '', 'root']],
            id: '1',
            pubkey: '1',
            sig: '1',
        );

        $response = $this->social->withKey('sk', 'pk')
                                 ->reply(
                                     event: $event,
                                     content: 'test',
                                     mentions: ['1']
                                 );

        $this->assertTrue($response->successful());
    }

    public function test_reaction()
    {
        Nostr::shouldReceive('event->publish->successful')->once()->andReturnTrue();

        $event = Event::makeSigned(
            kind: Kind::Text,
            content: 'test',
            created_at: 1,
            tags: [],
            id: '1',
            pubkey: '1',
            sig: '1',
        );

        $response = $this->social->withKey('sk', 'pk')
                                 ->reaction(
                                     event: $event,
                                     content: '+',
                                 );

        $this->assertTrue($response->successful());
    }

    public function test_delete()
    {
        Nostr::shouldReceive('event->publish->successful')->once()->andReturnTrue();

        $response = $this->social->withKey('sk', 'pk')
                                 ->delete(event_id: '1');

        $this->assertTrue($response->successful());
    }

    public function test_get_event_by_id()
    {
        $pk = Str::random(64);
        $id = Str::random(64);
        $sig = Str::random(128);

        Http::fake(fn () => Http::response([
            'event' => [
                'kind' => 1,
                'content' => '',
                'created_at' => 0,
                'tags' => [],
                'pubkey' => $pk,
                'id' => $id,
                'sig' => $sig,
            ],
        ]));

        $event = $this->social->withKey('sk', 'pk')
                              ->getEventById(id: $id);

        $this->assertSame([
            'id' => $id,
            'pubkey' => $pk,
            'sig' => $sig,
            'kind' => 1,
            'content' => '',
            'created_at' => 0,
            'tags' => [],
        ], $event->toArray());
    }

    public function test_get_event_by_id_http_failed()
    {
        Http::fake(fn () => Http::response('', 500));

        $this->expectException(RequestException::class);

        $event = $this->social->withKey('sk', 'pk')
                              ->getEventById(id: '1');
    }

    public function test_get_event_by_id_type_error()
    {
        Http::fake(fn () => Http::response(['event' => []]));

        $this->expectException(\TypeError::class);

        $event = $this->social->withKey('sk', 'pk')
                              ->getEventById(id: '1');
    }

    public function test_get_event_by_id_validator_fails()
    {
        Http::fake(fn () => Http::response([
            'event' => [
                'kind' => 1,
                'content' => '',
                'created_at' => 0,
                'tags' => [],
                'pubkey' => '',
                'id' => '',
                'sig' => '',
            ],
        ]));

        $this->expectException(EventNotFoundException::class);

        $event = $this->social->withKey('sk', 'pk')
                              ->getEventById(id: '1');
    }
}
