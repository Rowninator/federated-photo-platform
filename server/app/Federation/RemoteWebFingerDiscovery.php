<?php

namespace App\Federation;

use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class RemoteWebFingerDiscovery
{
    private const ACTIVITY_JSON = 'application/activity+json';

    private const ACTIVITY_STREAMS_JSON_LD = 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"';

    public function discover(string $input): RemoteWebFingerDiscoveryResult
    {
        $handle = RemoteWebFingerHandle::parse($input);
        $endpoint = "https://{$handle->domain()}/.well-known/webfinger";
        $this->validateRemoteHttpsUrl($endpoint);

        $response = Http::accept('application/jrd+json')
            ->connectTimeout(3)
            ->timeout(8)
            ->withoutRedirecting()
            ->get($endpoint, ['resource' => $handle->acct()]);

        if (! $response->successful() || $this->mediaType($response->header('Content-Type')) !== 'application/jrd+json') {
            throw new RuntimeException('Remote WebFinger discovery failed.');
        }

        $document = $response->json();

        if (! is_array($document)
            || ! is_string($document['subject'] ?? null)
            || ! is_array($document['links'] ?? null)) {
            throw new RuntimeException('Remote WebFinger response is invalid.');
        }

        $returnedSubject = $document['subject'];

        try {
            $subject = strncasecmp($returnedSubject, 'acct:', 5) === 0
                ? RemoteWebFingerHandle::parse($returnedSubject)
                : null;
        } catch (InvalidArgumentException) {
            $subject = null;
        }

        if ($subject === null || $subject->acct() !== $handle->acct()) {
            throw new RuntimeException('Remote WebFinger subject does not match the requested identity.');
        }

        $selfLinks = array_values(array_filter(
            $document['links'],
            fn (mixed $link): bool => is_array($link)
                && ($link['rel'] ?? null) === 'self'
                && is_string($link['type'] ?? null)
                && in_array($link['type'], [self::ACTIVITY_JSON, self::ACTIVITY_STREAMS_JSON_LD], true)
                && is_string($link['href'] ?? null),
        ));

        if (count($selfLinks) !== 1) {
            throw new RuntimeException('Remote WebFinger response has no unambiguous ActivityPub self link.');
        }

        $actorUri = $selfLinks[0]['href'];
        $this->validateRemoteHttpsUrl($actorUri);

        return new RemoteWebFingerDiscoveryResult(
            requestedAcct: $handle->acct(),
            returnedSubject: $returnedSubject,
            actorUri: $actorUri,
        );
    }

    private function mediaType(?string $contentType): string
    {
        return strtolower(trim(explode(';', $contentType ?? '', 2)[0]));
    }

    private function validateRemoteHttpsUrl(string $url): void
    {
        $parts = parse_url($url);

        if (! is_array($parts)
            || strcasecmp($parts['scheme'] ?? '', 'https') !== 0
            || ! is_string($parts['host'] ?? null)
            || isset($parts['user'])
            || isset($parts['pass'])) {
            throw new RuntimeException('Remote federation URL is not allowed.');
        }

        $host = strtolower(rtrim($parts['host'], '.'));
        $ipCandidate = trim($host, '[]');

        if ($host === ''
            || ! str_contains($host, '.')
            || filter_var($ipCandidate, FILTER_VALIDATE_IP) !== false
            || $this->isLocalOnlyHost($host)) {
            throw new RuntimeException('Remote federation host is not allowed.');
        }

        foreach (explode('.', $host) as $label) {
            if ($label === ''
                || strlen($label) > 63
                || preg_match('/\A[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\z/', $label) !== 1) {
                throw new RuntimeException('Remote federation host is not allowed.');
            }
        }
    }

    private function isLocalOnlyHost(string $host): bool
    {
        foreach (['localhost', 'local', 'internal', 'lan', 'home.arpa'] as $suffix) {
            if ($host === $suffix || str_ends_with($host, '.'.$suffix)) {
                return true;
            }
        }

        return false;
    }
}
