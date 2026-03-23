<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PaymentRepository;

final class IdempotencyService
{
    public function __construct(
        private readonly PaymentRepository $paymentRepository
    ) {
    }

    public function isDuplicate(string $merchantOperationNumber): bool
    {
        return $this->paymentRepository->exists($merchantOperationNumber);
    }
}
