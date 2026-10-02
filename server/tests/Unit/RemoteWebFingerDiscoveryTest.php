<?php

namespace Tests\Unit;

use App\Federation\RemoteWebFingerDiscovery;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class RemoteWebFingerDiscoveryTest extends TestCase
{
    public function test_it_requests_the_canonical_endpoint_and_selects_the_activity_json_self_link(): void
    {
        Http::fake([
            '*' => $this->jrd([
                'subject' => 'acct:alice@example.com',
                'links' => [
                    ['rel' => 'profile-page', 'type' => 'text/html', 'href' => 'https://example.com/@alice'],
                    ['rel' => 'self', 'type' => 'application/activity+json', 'href' => 'https://actors.example.com/users/alice'],
                ],
            ]),
        ]);

        $result = (new RemoteWebFingerDiscovery)->discover('@alice@EXAMPLE.COM');

        $this->assertSame('acct:alice@example.com', $result->requestedAcct());
        $this->assertSame('acct:alice@example.com', $result->returnedSubject());
        $this->assertSame('https://actors.example.com/users/alice', $result->actorUri());
        Http::assertSent(fn (Request $request): bool => $request->url()
            === 'https://example.com/.well-known/webfinger?resource=acct%3Aalice%40example.com'
            && $request->hasHeader('Accept', 'application/jrd+json'));
    }

    public function test_it_accepts_the_activitystreams_json_ld_self_link(): void
    {
        Http::fake([
            '*' => $this->jrd([
                'subject' => 'acct:alice@example.com',
                'links' => [[
                    'rel' => 'self',
                    'type' => 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"',
                    'href' => 'https://actors.example.com/users/alice',
                ]],
            ]),
        ]);

        $result = (new RemoteWebFingerDiscovery)->discover('alice@example.com');

        $this->assertSame('https://actors.example.com/users/alice', $result->actorUri());
    }

    public function test_it_rejects_a_missing_activitypub_self_link(): void
    {
        Http::fake([
            '*' => $this->jrd([
                'subject' => 'acct:alice@example.com',
                'links' => [[
                    'rel' => 'profile-page',
                    'type' => 'text/html',
                    'href' => 'https://example.com/@alice',
                ]],
            ]),
        ]);

        $this->expectException(RuntimeException::class);

        (new RemoteWebFingerDiscovery)->discover('alice@example.com');
    }

    public function test_it_rejects_a_mismatched_subject(): void
    {
        Http::fake([
            '*' => $this->jrd($this->validDocument(subject: 'acct:bob@example.com')),
        ]);

        $this->expectException(RuntimeException::class);

        (new RemoteWebFingerDiscovery)->discover('alice@example.com');
    }

    #[DataProvider('invalidResponses')]
    public function test_it_rejects_invalid_or_unsuccessful_responses(string $body, int $status, string $contentType): void
    {
        Http::fake([
            '*' => Http::response($body, $status, ['Content-Type' => $contentType]),
        ]);

        $this->expectException(RuntimeException::class);

        (new RemoteWebFingerDiscovery)->discover('alice@example.com');
    }

    #[DataProvider('unsafeActorUris')]
    public function test_it_rejects_unsafe_actor_uris(string $actorUri): void
    {
        Http::fake([
            '*' => $this->jrd($this->validDocument(actorUri: $actorUri)),
        ]);

        $this->expectException(RuntimeException::class);

        (new RemoteWebFingerDiscovery)->discover('alice@example.com');
    }

    public function test_it_rejects_an_obviously_local_discovery_host_before_sending_a_request(): void
    {
        Http::fake();

        try {
            (new RemoteWebFingerDiscovery)->discover('alice@server.local');
            $this->fail('Expected the local-only discovery host to be rejected.');
        } catch (RuntimeException) {
            Http::assertNothingSent();
        }
    }

    /**
     * @return array<string, array{string, int, string}>
     */
    public static function invalidResponses(): array
    {
        return [
            'malformed JSON' => ['{not-json', 200, 'application/jrd+json'],
            'non-JRD JSON' => ['{"subject":"acct:alice@example.com","links":[]}', 200, 'application/json'],
            'HTTP failure' => ['{}', 404, 'application/jrd+json'],
            'redirect is not followed' => ['{}', 302, 'application/jrd+json'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsafeActorUris(): array
    {
        return [
            'insecure scheme' => ['http://actors.example.com/users/alice'],
            'localhost' => ['https://localhost/users/alice'],
            'single-label host' => ['https://actor/users/alice'],
            'local-only host' => ['https://actors.local/users/alice'],
            'IPv4 literal' => ['https://127.0.0.1/users/alice'],
            'IPv6 literal' => ['https://[::1]/users/alice'],
            'credentials' => ['https://user:password@actors.example.com/users/alice'],
        ];
    }

    /**
     * @return array{subject: string, links: array<int, array{rel: string, type: string, href: string}>}
     */
    private function validDocument(
        string $subject = 'acct:alice@example.com',
        string $actorUri = 'https://actors.example.com/users/alice',
    ): array {
        return [
            'subject' => $subject,
            'links' => [[
                'rel' => 'self',
                'type' => 'application/activity+json',
                'href' => $actorUri,
            ]],
        ];
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function jrd(array $document): mixed
    {
        return Http::response(
            json_encode($document, JSON_THROW_ON_ERROR),
            200,
            ['Content-Type' => 'application/jrd+json'],
        );
    }
}
