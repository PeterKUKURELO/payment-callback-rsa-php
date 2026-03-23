<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PaymentRepository;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

final class PaymentService
{
    private const ALLOWED_TRANSACTION_STATES = ['AUTORIZADO', 'DENEGADO', 'INVALIDO'];

    public function __construct(
        private readonly PaymentRepository $paymentRepository,
        private readonly IdempotencyService $idempotencyService
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function process(array $data): array
    {
        $success = $this->normalizeSuccess($data['success'] ?? null);
        $merchantCode = trim((string) ($data['merchant_code'] ?? ''));
        $merchantOperationNumber = trim((string) ($data['merchant_operation_number'] ?? ''));
        $transaction = $data['transaction'] ?? null;
        $meta = $data['meta'] ?? null;

        if ($merchantCode === '') {
            throw new InvalidArgumentException('Field merchant_code is required.');
        }

        if (!preg_match('/^\d{6,}$/', $merchantOperationNumber)) {
            throw new InvalidArgumentException('Field merchant_operation_number must be numeric with at least 6 digits.');
        }

        if (!is_array($transaction)) {
            throw new InvalidArgumentException('Field transaction is required and must be an object.');
        }

        if (!is_array($meta)) {
            throw new InvalidArgumentException('Field meta is required and must be an object.');
        }

        $transactionId = trim((string) ($transaction['transaction_id'] ?? ''));
        if ($transactionId === '') {
            throw new InvalidArgumentException('Field transaction.transaction_id is required.');
        }

        $state = strtoupper(trim((string) ($transaction['state'] ?? '')));
        if (!in_array($state, self::ALLOWED_TRANSACTION_STATES, true)) {
            throw new InvalidArgumentException('Field transaction.state must be AUTORIZADO, DENEGADO or INVALIDO.');
        }

        $currency = trim((string) ($transaction['currency'] ?? ''));
        if ($currency === '' || !preg_match('/^\d+$/', $currency)) {
            throw new InvalidArgumentException('Field transaction.currency is required and must be numeric.');
        }

        $amount = trim((string) ($transaction['amount'] ?? ''));
        if ($amount === '' || !preg_match('/^\d+$/', $amount)) {
            throw new InvalidArgumentException('Field transaction.amount is required and must be numeric (in cents).');
        }

        $metaStatus = $meta['status'] ?? null;
        if (!is_array($metaStatus)) {
            throw new InvalidArgumentException('Field meta.status is required and must be an object.');
        }

        $metaCode = trim((string) ($metaStatus['code'] ?? ''));
        if ($metaCode === '') {
            throw new InvalidArgumentException('Field meta.status.code is required.');
        }

        $messageIlgn = $metaStatus['message_ilgn'] ?? null;
        if (!is_array($messageIlgn) || $messageIlgn === []) {
            throw new InvalidArgumentException('Field meta.status.message_ilgn is required and must be a non-empty array.');
        }

        foreach ($messageIlgn as $index => $entry) {
            if (!is_array($entry)) {
                throw new InvalidArgumentException(sprintf('Field meta.status.message_ilgn[%d] must be an object.', $index));
            }

            $locale = trim((string) ($entry['locale'] ?? ''));
            $value = trim((string) ($entry['value'] ?? ''));
            if ($locale === '' || $value === '') {
                throw new InvalidArgumentException(sprintf('Fields locale and value are required in meta.status.message_ilgn[%d].', $index));
            }
        }

        if ($this->idempotencyService->isDuplicate($merchantOperationNumber)) {
            error_log(sprintf('Duplicate callback ignored for operation %s', $merchantOperationNumber));

            return [
                'processed' => true,
                'duplicate' => true,
                'success' => $success,
                'merchant_code' => $merchantCode,
                'merchant_operation_number' => $merchantOperationNumber,
                'transaction_state' => $state,
                'transaction_id' => $transactionId,
            ];
        }

        $record = [
            'success' => $success,
            'action' => $data['action'] ?? null,
            'merchant_code' => $merchantCode,
            'merchant_operation_number' => $merchantOperationNumber,
            'transaction_id' => $transactionId,
            'transaction_state' => $state,
            'amount' => $amount,
            'currency' => $currency,
            'meta_status_code' => $metaCode,
            'processor_response' => $transaction['processor_response'] ?? null,
            'processed_at' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
            'payload' => $data,
        ];

        $this->paymentRepository->save($record);
        error_log(sprintf('Payment processed successfully for operation %s', $merchantOperationNumber));

        return [
            'processed' => true,
            'duplicate' => false,
            'success' => $success,
            'merchant_code' => $merchantCode,
            'merchant_operation_number' => $merchantOperationNumber,
            'transaction_state' => $state,
            'transaction_id' => $transactionId,
            'meta_status_code' => $metaCode,
        ];
    }

    private function normalizeSuccess(mixed $rawSuccess): bool
    {
        if (is_bool($rawSuccess)) {
            return $rawSuccess;
        }

        if (is_string($rawSuccess)) {
            $normalized = strtolower(trim($rawSuccess));
            if ($normalized === 'true') {
                return true;
            }

            if ($normalized === 'false') {
                return false;
            }
        }

        throw new InvalidArgumentException('Field success is required and must be true or false.');
    }
}
