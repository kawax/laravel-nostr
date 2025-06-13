<?php

declare(strict_types=1);

namespace Revolution\Nostr\Social;

use Exception;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Macroable;
use Revolution\Nostr\Event;
use Revolution\Nostr\Exceptions\EventNotFoundException;
use Revolution\Nostr\Facades\Nostr;
use Revolution\Nostr\Filter;
use Revolution\Nostr\Kind;
use Revolution\Nostr\Profile;
use Revolution\Nostr\Tags\EventTag;
use Revolution\Nostr\Tags\HashTag;
use Revolution\Nostr\Tags\PersonTag;
use Revolution\Nostr\Tags\ReferenceTag;

/**
 * Implementation for social networking.
 */
class SocialClient
{
    use Conditionable;
    use Macroable;

    protected string $driver;

    protected string $relay;

    protected string $sk;

    protected string $pk;

    public function __construct()
    {
        $this->driver(Config::get('nostr.driver') ?? 'native');

        $this->relay = Config::get('nostr.relays.0') ?? '';
    }

    public function driver(string $driver): self
    {
        $this->driver = $driver;

        return $this;
    }

    public function withRelay(string $relay): static
    {
        $this->relay = $relay;

        return $this;
    }

    public function withKey(#[\SensitiveParameter] string $sk = '', string $pk = ''): static
    {
        $this->sk = $sk;
        $this->pk = $pk;

        return $this;
    }

    public function publishEvent(Event $event): Response
    {
        return Nostr::driver($this->driver)
            ->event()
            ->publish(event: $event, sk: $this->sk, relay: $this->relay);
    }

    /**
     * @throws Exception|RequestException
     */
    public function createNewUser(Profile $profile): array
    {
        $keys = Nostr::driver($this->driver)->key()->generate()->collect();

        if (! $keys->has(['sk', 'pk'])) {
            throw new Exception('Failed create new user.');
        }

        $this->withKey(sk: $keys->get('sk'), pk: $keys->get('pk'));

        $this->updateFollows(follows: [PersonTag::make(p: $this->pk)])->throw();

        $this->updateProfile(profile: $profile)->throw();

        return [
            'keys' => $keys->toArray(),
            'profile' => $profile->toArray(),
        ];
    }

    public function updateProfile(Profile $profile): Response
    {
        $event = new Event(
            kind: Kind::Metadata,
            content: $profile->toJson(),
            created_at: now()->timestamp,
        );

        return $this->publishEvent(event: $event);
    }

    public function profile(?string $pk = null): array
    {
        $pk = $pk ?? $this->pk;

        $filter = new Filter(
            authors: [$pk],
            kinds: [Kind::Metadata],
        );

        return Nostr::driver($this->driver)
            ->event()
            ->get(filter: $filter, relay: $this->relay)
            ->json('event') ?? [];
    }

    /**
     * @return array<string>
     */
    public function follows(): array
    {
        $filter = new Filter(
            authors: [$this->pk],
            kinds: [Kind::Contacts],
        );

        $response = Nostr::driver($this->driver)->event()->get(filter: $filter, relay: $this->relay);

        return $response->collect('event.tags')
            ->mapToGroups(fn ($tag) => [$tag[0] => $tag[1]])
            ->get('p')
            ?->toArray() ?? [];
    }

    /**
     * @param  array<PersonTag|array>  $follows  Must include all follows.
     */
    public function updateFollows(array $follows): Response
    {
        $event = new Event(
            kind: Kind::Contacts,
            content: '',
            created_at: now()->timestamp,
            tags: collect($follows)->toArray(),
        );

        return $this->publishEvent(event: $event);
    }

    public function relays(): array
    {
        $filter = new Filter(
            authors: [$this->pk],
            kinds: [Kind::RelayList],
        );

        return Nostr::driver($this->driver)
            ->event()
            ->get(filter: $filter, relay: $this->relay)
            ->json('event') ?? [];
    }

    /**
     * @param  array<string>  $relays
     */
    public function updateRelays(array $relays = []): Response
    {
        $relays = blank($relays) ? config('nostr.relays') : $relays;

        $relays = collect($relays)
            ->map(fn (string $relay) => ReferenceTag::make(r: $relay));

        $event = new Event(
            kind: Kind::RelayList,
            content: '',
            created_at: now()->timestamp,
            tags: $relays->toArray(),
        );

        return $this->publishEvent(event: $event);
    }

    /**
     * @param  array<string>  $authors
     */
    public function profiles(array $authors): array
    {
        $filter = new Filter(
            authors: $authors,
            kinds: [Kind::Metadata],
        );

        return Nostr::driver($this->driver)
            ->event()
            ->list(filter: $filter, relay: $this->relay)
            ->json('events') ?? [];
    }

    /**
     * @param  array<string>  $authors
     */
    public function notes(array $authors, array $kinds = [Kind::Text, Kind::Article], ?int $since = null, ?int $until = null, ?int $limit = null): array
    {
        $filter = new Filter(
            authors: $authors,
            kinds: $kinds,
            since: $since,
            until: $until,
            limit: $limit,
        );

        $response = Nostr::driver($this->driver)
            ->event()
            ->list(filter: $filter, relay: $this->relay);

        return $response->collect('events')
            ->sortByDesc('created_at')
            ->toArray() ?? [];
    }

    public function mergeNotesAndProfiles(array $notes, array $profiles): array
    {
        return collect($notes)
            ->filter(fn ($note) => Arr::exists($note, 'pubkey'))
            ->map(function ($note) use ($profiles) {
                $profile = collect($profiles)->firstWhere('pubkey', $note['pubkey']);

                if (! Arr::exists($profile, 'content')) {
                    return $note;
                }

                $user = json_decode(Arr::get($profile, 'content', '[]'), true);

                return array_merge($note, $user);
            })->toArray();
    }

    public function timeline(?int $since = null, ?int $until = null, int $limit = 10): array
    {
        $follows = $this->follows();

        $profiles = $this->profiles(authors: $follows);

        $notes = $this->notes(authors: $follows, since: $since, until: $until, limit: $limit);

        return $this->mergeNotesAndProfiles($notes, $profiles);
    }

    /**
     * If you need a more complex creation method, use macro() or publishEvent() directly.
     */
    public function createNote(string $content, array $tags = []): Response
    {
        $event = new Event(
            kind: Kind::Text,
            content: $content,
            created_at: now()->timestamp,
            tags: collect($tags)->toArray(),
        );

        return $this->publishEvent(event: $event);
    }

    public function createNoteTo(string $content, string $pk): Response
    {
        $event = new Event(
            kind: Kind::Text,
            content: $content,
            created_at: now()->timestamp,
            tags: [PersonTag::make(p: $pk)->toArray()],
        );

        return $this->publishEvent(event: $event);
    }

    /**
     * @param  array<string>  $hashtags
     */
    public function createNoteWithHashTag(string $content, array $hashtags = []): Response
    {
        $tags = collect();

        foreach ($hashtags as $hashtag) {
            $tags->push(HashTag::make(t: $hashtag)->toArray());
        }

        $event = new Event(
            kind: Kind::Text,
            content: $content,
            created_at: now()->timestamp,
            tags: $tags->toArray(),
        );

        return $this->publishEvent(event: $event);
    }

    /**
     * @param  Event  $event  Parent Event
     * @param  array<string>  $mentions
     * @param  array<string>  $hashtags
     */
    public function reply(Event $event, string $content, array $mentions = [], array $hashtags = []): Response
    {
        $rootId = $event->rootId();

        $tags = collect()
            ->when(filled($rootId),
                fn (Collection $collection) => $collection->push(
                    EventTag::make(id: $rootId, relay: $this->relay, marker: 'root'),
                    EventTag::make(id: $event->id, relay: $this->relay, marker: 'reply'),
                ),
                fn (Collection $collection) => $collection->push(
                    EventTag::make(id: $event->id, relay: $this->relay, marker: 'root'),
                ));

        foreach ($mentions as $pk) {
            $tags->push(PersonTag::make(p: $pk)->toArray());
        }

        foreach ($hashtags as $hashtag) {
            $tags->push(HashTag::make(t: $hashtag)->toArray());
        }

        $reply_event = new Event(
            kind: Kind::Text,
            content: $content,
            created_at: now()->timestamp,
            tags: $tags->toArray(),
        );

        return $this->publishEvent(event: $reply_event);
    }

    public function reaction(Event $event, string $content = '+'): Response
    {
        $tags = collect([
            EventTag::make(id: $event->id, relay: $this->relay, marker: 'reply'),
            PersonTag::make(p: $event->pubkey, relay: $this->relay),
        ]);

        $reaction_event = new Event(
            kind: Kind::Reaction,
            content: $content,
            created_at: now()->timestamp,
            tags: $tags->toArray(),
        );

        return $this->publishEvent(event: $reaction_event);
    }

    public function delete(string $event_id): Response
    {
        $e = EventTag::make(id: $event_id);

        $event = new Event(
            kind: Kind::EventDeletion,
            created_at: now()->timestamp,
            tags: [$e->toArray()],
        );

        return $this->publishEvent(event: $event);
    }

    public function getEventById(string $id): Event
    {
        $res = Nostr::driver($this->driver)
            ->event()
            ->get(filter: Filter::make(ids: [$id]), relay: $this->relay);

        return Event::fromArray(event: $res->json('event'))
            ->tap(fn (Event $event) => throw_unless($event->validate(), EventNotFoundException::class));
    }
}
