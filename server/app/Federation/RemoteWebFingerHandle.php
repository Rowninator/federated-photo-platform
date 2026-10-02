<?php

namespace App\Federation;

use InvalidArgumentException;

final readonly class RemoteWebFingerHandle
{
    private function __construct(
        private string $username,
        private string $domain,
    ) {}

    public static function parse(string $input): self
    {
        if ($input === '' || preg_match('/\s/u', $input) === 1) {
            self::reject();
        }

        if (strncasecmp($input, 'acct:', 5) === 0) {
            $input = substr($input, 5);
        } elseif (str_starts_with($input, '@')) {
            $input = substr($input, 1);
        }

        if (substr_count($input, '@') !== 1) {
            self::reject();
        }

        [$username, $domain] = explode('@', $input, 2);

        if ($username === '' || $domain === '') {
            self::reject();
        }

        $domain = strtolower($domain);
        self::validateDomain($domain);

        return new self($username, $domain);
    }

    public function username(): string
    {
        return $this->username;
    }

    public function domain(): string
    {
        return $this->domain;
    }

    public function acct(): string
    {
        return "acct:{$this->username}@{$this->domain}";
    }

    private static function validateDomain(string $domain): void
    {
        $ipCandidate = trim($domain, '[]');

        if (filter_var($ipCandidate, FILTER_VALIDATE_IP) !== false
            || strlen($domain) > 253
            || ! str_contains($domain, '.')) {
            self::reject();
        }

        foreach (explode('.', $domain) as $label) {
            if ($label === ''
                || strlen($label) > 63
                || preg_match('/\A[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\z/', $label) !== 1) {
                self::reject();
            }
        }
    }

    private static function reject(): never
    {
        throw new InvalidArgumentException('Invalid remote WebFinger handle.');
    }
}
