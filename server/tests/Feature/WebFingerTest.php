<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WebFingerTest extends TestCase
{
    use RefreshDatabase;

    public function test_known_local_profile_returns_minimal_canonical_jrd(): void
    {
        $this->configureFederation();
        $user = User::factory()->create(['email' => 'alice.private@example.test']);
        Profile::create([
            'user_id' => $user->id,
            'username' => 'Alice',
            'display_name' => 'Alice Example',
            'bio' => 'Not part of WebFinger.',
        ]);

        $response = $this->get('/.well-known/webfinger?'.http_build_query([
            'resource' => 'acct:ALICE@PHOTOS.EXAMPLE',
        ]));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/jrd+json')
            ->assertHeader('Access-Control-Allow-Origin', '*')
            ->assertExactJson([
                'subject' => 'acct:alice@photos.example',
                'links' => [[
                    'rel' => 'self',
                    'type' => 'application/activity+json',
                    'href' => 'https://photos.example/users/alice',
                ]],
            ]);
        $this->assertStringNotContainsString($user->email, $response->getContent());
    }

    #[DataProvider('malformedResources')]
    public function test_missing_or_malformed_resource_is_rejected(?string $resource): void
    {
        $this->configureFederation();
        $query = $resource === null ? '' : '?'.http_build_query(['resource' => $resource]);

        $this->getJson('/.well-known/webfinger'.$query)->assertStatus(400);
    }

    public function test_non_local_domain_is_not_found(): void
    {
        $this->configureFederation();
        Profile::create([
            'user_id' => User::factory()->create()->id,
            'username' => 'alice',
        ]);

        $this->getJson('/.well-known/webfinger?'.http_build_query([
            'resource' => 'acct:alice@elsewhere.example',
        ]))->assertNotFound();
    }

    public function test_unknown_local_username_is_not_found_without_resolving_another_profile(): void
    {
        $this->configureFederation();
        Profile::create([
            'user_id' => User::factory()->create()->id,
            'username' => 'alice',
        ]);

        $response = $this->getJson('/.well-known/webfinger?'.http_build_query([
            'resource' => 'acct:this-user-does-not-exist@photos.example',
        ]));

        $response->assertNotFound();
        $this->assertStringNotContainsString('acct:alice@photos.example', $response->getContent());
    }

    /**
     * @return array<string, array{string|null}>
     */
    public static function malformedResources(): array
    {
        return [
            'missing' => [null],
            'wrong scheme' => ['https://photos.example/users/alice'],
            'missing username' => ['acct:@photos.example'],
            'missing domain' => ['acct:alice@'],
            'multiple separators' => ['acct:alice@photos.example@elsewhere.example'],
        ];
    }

    private function configureFederation(): void
    {
        config()->set([
            'federation.domain' => 'photos.example',
            'federation.base_url' => 'https://photos.example',
        ]);
    }
}
