<?php

declare(strict_types=1);

namespace Tests\Feature\Client\Node;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Revolution\Nostr\Facades\Nostr;
use Tests\TestCase;

class ClientNip04Test extends TestCase
{
    public function test_nip04_encrypt()
    {
        Http::fake(fn () => Http::response(['encrypt' => 'encrypt text']));

        $res = Nostr::driver('node')
            ->nip04()->encrypt(sk: 'sk', pk: 'pk', content: 'content');

        $this->assertSame(['encrypt' => 'encrypt text'], $res->json());

        Http::assertSent(fn (Request $request) => $request['sk'] === 'sk');
    }

    public function test_nip04_decrypt()
    {
        Http::fake(fn () => Http::response(['decrypt' => 'decrypt text']));

        $res = Nostr::driver('node')
            ->nip04()->decrypt(sk: 'sk', pk: 'pk', content: 'content');

        $this->assertSame(['decrypt' => 'decrypt text'], $res->json());

        Http::assertSent(fn (Request $request) => $request['sk'] === 'sk');
    }
}
