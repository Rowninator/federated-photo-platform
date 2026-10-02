<?php

namespace Tests\Unit;

use App\Federation\LocalFederationIdentity;
use App\Models\Profile;
use Tests\TestCase;

class LocalFederationIdentityTest extends TestCase
{
    public function test_it_builds_local_identifiers_from_canonical_federation_configuration(): void
    {
        config()->set([
            'app.url' => 'https://request-host.example',
            'federation.domain' => 'photos.example',
            'federation.base_url' => 'https://social.example/',
        ]);

        $profile = new Profile(['username' => 'Alice']);
        $identity = LocalFederationIdentity::fromProfile($profile);

        $this->assertSame('acct:alice@photos.example', $identity->acct());
        $this->assertSame('https://social.example/users/alice', $identity->actorUrl());
    }
}
