<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\PaymentService;
use App\Services\SignatureService;
use InvalidArgumentException;
use JsonException;
use Throwable;

final class PaymentController
{
    public function __construct(
        private readonly SignatureService $signatureService,
        private readonly PaymentService $paymentService
    ) {
    }

    /**
     * @param array<string, mixed> $headers
     * @return array{statusCode:int, body:array<string, mixed>}
     */
    public function handle(string $rawBody, array $headers): array
    {
        $payload = null;

        try {
            $signature = $this->getHeaderValue($headers, 'signature');
            if ($signature === null || trim($signature) === '') {
                return $this->response(400, [
                    'message' => 'Missing signature header.',
                ]);
            }

            if (!$this->signatureService->verify($rawBody, $signature)) {
                return $this->response(400, [
                    'message' => 'Invalid signature.',
                ]);
            }

            $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($payload)) {
                return $this->response(400, [
                    'message' => 'Invalid JSON payload.',
                ]);
            }

            $result = $this->paymentService->process($payload);

            return $this->response(200, [
                'message' => 'Callback processed successfully.',
                'data' => $result,
            ]);
        } catch (JsonException $exception) {
            error_log(sprintf('JSON decode error: %s', $exception->getMessage()));
            $this->logUnexpectedFormat($rawBody, null, 'Malformed JSON body.');
            return $this->response(400, [
                'message' => 'Malformed JSON body.',
            ]);
        } catch (InvalidArgumentException $exception) {
            error_log(sprintf('Validation error: %s', $exception->getMessage()));
            $this->logUnexpectedFormat($rawBody, is_array($payload) ? $payload : null, $exception->getMessage());
            return $this->response(400, [
                'message' => $exception->getMessage(),
            ]);
        } catch (Throwable $exception) {
            error_log(sprintf('Unhandled callback error: %s', $exception->getMessage()));
            return $this->response(500, [
                'message' => 'Internal server error.',
            ]);
        }
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
                return isset($value[0]) ? (string) $value[0] : null;
            }

            return (string) $value;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $body
     * @return array{statusCode:int, body:array<string, mixed>}
     */
    private function response(int $statusCode, array $body): array
    {
        return [
            'statusCode' => $statusCode,
            'body' => $body,
        ];
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    private function logUnexpectedFormat(string $rawBody, ?array $payload, string $reason): void
    {
        $keys = $payload !== null ? implode(',', array_keys($payload)) : 'N/A';
        $preview = substr($rawBody, 0, 1200);
        $preview = str_replace(["\r", "\n"], ['\\r', '\\n'], $preview);

        error_log(sprintf(
            'Unexpected callback format. reason="%s" top_level_keys="%s" raw_preview="%s"',
            $reason,
            $keys,
            $preview
        ));
    }
}
