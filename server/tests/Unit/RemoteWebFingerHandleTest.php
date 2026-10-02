<?php

namespace Tests\Unit;

use App\Federation\RemoteWebFingerHandle;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RemoteWebFingerHandleTest extends TestCase
{
    #[DataProvider('acceptedHandles')]
    public function test_it_normalizes_supported_input_forms(string $input): void
    {
        $this->assertSame(
            'acct:alice@example.com',
            RemoteWebFingerHandle::parse($input)->acct(),
        );
    }

    public function test_it_lowercases_only_the_domain(): void
    {
        $handle = RemoteWebFingerHandle::parse('Alice@EXAMPLE.COM');

        $this->assertSame('Alice', $handle->username());
        $this->assertSame('example.com', $handle->domain());
        $this->assertSame('acct:Alice@example.com', $handle->acct());
    }

    #[DataProvider('invalidHandles')]
    public function test_it_rejects_invalid_or_non_remote_handles(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);

        RemoteWebFingerHandle::parse($input);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function acceptedHandles(): array
    {
        return [
            'bare' => ['alice@example.com'],
            'leading at sign' => ['@alice@example.com'],
            'acct URI' => ['acct:alice@example.com'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidHandles(): array
    {
        return [
            'missing username' => ['acct:@example.com'],
            'missing domain' => ['alice@'],
            'whitespace' => ['alice @example.com'],
            'localhost' => ['alice@localhost'],
            'single-label host' => ['alice@development'],
            'IPv4 literal' => ['alice@192.0.2.1'],
            'IPv6 literal' => ['alice@[2001:db8::1]'],
            'empty domain label' => ['alice@example..com'],
            'invalid domain label' => ['alice@-example.com'],
        ];
    }
}
