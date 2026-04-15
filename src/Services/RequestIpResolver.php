<?php

declare(strict_types=1);

namespace App\Services;

final class RequestIpResolver
{
    /**
     * @param array<string, mixed> $server
     * @param array<string, mixed> $headers
     */
    public function fromPhpRequest(array $server, array $headers = []): ?string
    {
        return $this->resolveCandidate([
            $this->getHeaderValue($headers, 'x-forwarded-for'),
            $this->getHeaderValue($headers, 'x-real-ip'),
            $this->getServerValue($server, 'HTTP_X_FORWARDED_FOR'),
            $this->getServerValue($server, 'HTTP_X_REAL_IP'),
            $this->getServerValue($server, 'REMOTE_ADDR'),
        ]);
    }

    /**
     * @param array<string, mixed> $event
     */
    public function fromLambdaEvent(array $event): ?string
    {
        $headers = is_array($event['headers'] ?? null) ? $event['headers'] : [];
        $requestContext = is_array($event['requestContext'] ?? null) ? $event['requestContext'] : [];
        $http = is_array($requestContext['http'] ?? null) ? $requestContext['http'] : [];
        $identity = is_array($requestContext['identity'] ?? null) ? $requestContext['identity'] : [];

        return $this->resolveCandidate([
            is_string($http['sourceIp'] ?? null) ? $http['sourceIp'] : null,
            is_string($identity['sourceIp'] ?? null) ? $identity['sourceIp'] : null,
            $this->getHeaderValue($headers, 'x-forwarded-for'),
            $this->getHeaderValue($headers, 'x-real-ip'),
        ]);
    }

    /**
     * @param list<mixed> $candidates
     */
    private function resolveCandidate(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            $ip = $this->normalizeCandidate($candidate);
            if ($ip !== null) {
                return $ip;
            }
        }

        return null;
    }

    private function normalizeCandidate(mixed $candidate): ?string
    {
        if (!is_string($candidate)) {
            return null;
        }

        foreach (explode(',', $candidate) as $part) {
            $ip = trim($part);
            if ($ip === '') {
                continue;
            }

            if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                return $ip;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $headers
     */
    private function getHeaderValue(array $headers, string $targetHeader): ?string
    {
        $targetHeader = strtolower($targetHeader);

        foreach ($headers as $name => $value) {
            if (strtolower((string) $name) !== $targetHeader) {
                continue;
            }

            if (is_array($value)) {
                return isset($value[0]) && is_string($value[0]) ? $value[0] : null;
            }

            return is_string($value) ? $value : null;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $server
     */
    private function getServerValue(array $server, string $key): ?string
    {
        $value = $server[$key] ?? null;

        return is_string($value) ? $value : null;
    }
}
