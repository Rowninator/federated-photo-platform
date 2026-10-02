<?php

namespace App\Federation;

use App\Models\Profile;

final readonly class LocalFederationIdentity
{
    private function __construct(
        private string $username,
        private string $domain,
        private string $baseUrl,
    ) {}

    public static function fromProfile(Profile $profile): self
    {
        return new self(
            username: $profile->username,
            domain: (string) config('federation.domain'),
            baseUrl: rtrim((string) config('federation.base_url'), '/'),
        );
    }

    public function acct(): string
    {
        return "acct:{$this->username}@{$this->domain}";
    }

    public function actorUrl(): string
    {
        return "{$this->baseUrl}/users/{$this->username}";
    }
}
