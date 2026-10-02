<?php

namespace App\Federation;

final readonly class RemoteWebFingerDiscoveryResult
{
    public function __construct(
        private string $requestedAcct,
        private string $returnedSubject,
        private string $actorUri,
    ) {}

    public function requestedAcct(): string
    {
        return $this->requestedAcct;
    }

    public function returnedSubject(): string
    {
        return $this->returnedSubject;
    }

    public function actorUri(): string
    {
        return $this->actorUri;
    }
}
