<?php

declare(strict_types=1);

namespace Tests;

use App\Services\RequestIpResolver;
use PHPUnit\Framework\TestCase;

final class RequestIpResolverTest extends TestCase
{
    public function testFromPhpRequestUsesFirstForwardedIp(): void
    {
        $resolver = new RequestIpResolver();

        $ip = $resolver->fromPhpRequest(
            ['REMOTE_ADDR' => '10.0.0.5'],
            ['X-Forwarded-For' => '198.51.100.24, 10.0.0.5']
        );

        self::assertSame('198.51.100.24', $ip);
    }

    public function testFromPhpRequestFallsBackToRemoteAddr(): void
    {
        $resolver = new RequestIpResolver();

        $ip = $resolver->fromPhpRequest([
            'REMOTE_ADDR' => '203.0.113.40',
        ]);

        self::assertSame('203.0.113.40', $ip);
    }

    public function testFromLambdaEventUsesRequestContextSourceIp(): void
    {
        $resolver = new RequestIpResolver();

        $ip = $resolver->fromLambdaEvent([
            'headers' => [
                'x-forwarded-for' => '198.51.100.99, 10.0.0.5',
            ],
            'requestContext' => [
                'http' => [
                    'sourceIp' => '203.0.113.12',
                ],
            ],
        ]);

        self::assertSame('203.0.113.12', $ip);
    }
}
