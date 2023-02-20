<?php
declare(strict_types=1);

namespace Revolution\Nostr\Social;

use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Macroable;
use Revolution\Nostr\Event;
use Revolution\Nostr\Facades\Nostr;
use Revolution\Nostr\Filter;
use Revolution\Nostr\Kind;
use Revolution\Nostr\Profile;
use Revolution\Nostr\Tag\EventTag;
use Revolution\Nostr\Tag\HashTag;
use Revolution\Nostr\Tag\PersonTag;

/**
 * Implementation for social networking.
 */
class SocialClient
{
    use Macroable, Conditionable;

    protected string $relay;
    protected string $sk;
    protected string $pk;

    public function __construct()
    {
        $this->relay = Arr::first((Config::get('nostr.relays')));
    }

    public function withRelay(string $relay): static
    {
        $this->relay = $relay;

        return $this;
    }

    public function withKey(string $sk = '', string $pk = ''): static
    {
        $this->sk = $sk;
        $this->pk = $pk;

        return $this;
    }

    public function publishEvent(Event $event): Response
    {
        return Nostr::event()->publish(event: $event, sk: $this->sk, relay: $this->relay);
    }

    /**
     * @throws Exception
     */
    public function createNewUser(Profile $profile): array
    {
        $keys = Nostr::key()->generate()->collect();

        if ($keys->has(['sk', 'pk'])) {
            $this->withKey(sk: $keys->get('sk'), pk: $keys->get('pk'));

            $response = $this->updateProfile($profile);

            if ($response->successful()) {
                return [
                    'keys' => $keys->toArray(),
                    'profile' => $profile->toArray(),
                ];
            }
        }

        throw new Exception('Failed create new user.');
    }

    public function updateProfile(Profile $profile): Response
    {
        $event = new Event(
            kind: Kind::Metadata->value,
            content: $profile->toJson(),
            created_at: now()->timestamp,
        );

        return $this->publishEvent(event: $event);
    }

    public function profile(?string $pk = null): Response
    {
        $pk = $pk ?? $this->pk;

        $filter = new Filter(
            authors: [$pk],
            kinds: [Kind::Metadata],
        );

        return Nostr::event()->get(filter: $filter, relay: $this->relay);
    }

    public function follows(): array
    {
        $filter = new Filter(
            authors: [$this->pk],
            kinds: [Kind::Contacts],
        );

        $response = Nostr::event()->get(filter: $filter, relay: $this->relay);

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
            kind: Kind::Contacts->value,
            content: '',
            created_at: now()->timestamp,
            tags: collect($follows)->toArray(),
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

        $response = Nostr::event()->list(filters: [$filter], relay: $this->relay);

        return $response->json('events') ?? [];
    }

    /**
     * @param  array<string>  $authors
     */
    public function notes(array $authors, ?int $since = null, ?int $until = null, ?int $limit = null): array
    {
        $filter = new Filter(
            authors: $authors,
            kinds: [Kind::Text],
            since: $since,
            until: $until,
            limit: $limit,
        );

        $response = Nostr::event()->list(filters: [$filter], relay: $this->relay);

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

    public function timeline(?int $since = null, ?int $until = null, ?int $limit = 10): array
    {
        $follows = $this->follows();

        $profiles = $this->profiles(authors: $follows);

        $notes = $this->notes(authors: $follows, since: $since, until: $until, limit: $limit);

        return $this->mergeNotesAndProfiles($notes, $profiles);
    }

    /**
     * If you need a more complex creation method, use macro() or publishEvent() directly.
     */
    public function createTextNote(string $content, array $tags = []): Response
    {
        $event = new Event(
            kind: Kind::Text->value,
            content: $content,
            created_at: now()->timestamp,
            tags: $tags,
        );

        return $this->publishEvent(event: $event);
    }

    public function createTextNoteTo(string $content, string $pk): Response
    {
        $event = new Event(
            kind: Kind::Text->value,
            content: $content,
            created_at: now()->timestamp,
            tags: [(new PersonTag(pubkey: $pk))->toArray()],
        );

        return $this->publishEvent(event: $event);
    }

    public function createTextNoteWithHashTag(string $content, array $hashtags = []): Response
    {
        $tags = collect();

        foreach ($hashtags as $hashtag) {
            $tags->push((new HashTag(hashtag: $hashtag))->toArray());
        }

        $event = new Event(
            kind: Kind::Text->value,
            content: $content,
            created_at: now()->timestamp,
            tags: $tags->toArray(),
        );

        return $this->publishEvent(event: $event);
    }

    public function reply(string $content, string $event_id, string $marker = 'root', array $pks = []): Response
    {
        $tags = collect([
            new EventTag(
                id: $event_id,
                relay: $this->relay,
                marker: $marker,
            ),
        ]);

        foreach ($pks as $pk) {
            $tags->push(new PersonTag(pubkey: $pk));
        }

        $event = new Event(
            kind: Kind::Text->value,
            content: $content,
            created_at: now()->timestamp,
            tags: $tags->toArray(),
        );

        return $this->publishEvent(event: $event);
    }
}