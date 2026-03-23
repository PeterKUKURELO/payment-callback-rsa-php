<?php

declare(strict_types=1);

namespace App\Repositories;

final class PaymentRepository
{
    /**
     * In-memory storage for demo/local use.
     * Replace this with DynamoDB or RDS implementation in production.
     *
     * @var array<string, array<string, mixed>>
     */
    private static array $payments = [];

    public function exists(string $merchantOperationNumber): bool
    {
        return isset(self::$payments[$merchantOperationNumber]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByMerchantOperationNumber(string $merchantOperationNumber): ?array
    {
        return self::$payments[$merchantOperationNumber] ?? null;
    }

    /**
     * @param array<string, mixed> $payment
     */
    public function save(array $payment): void
    {
        $operationNumber = (string) ($payment['merchant_operation_number'] ?? '');
        if ($operationNumber === '') {
            throw new \InvalidArgumentException('merchant_operation_number is required to persist payment.');
        }

        self::$payments[$operationNumber] = $payment;
    }
}
