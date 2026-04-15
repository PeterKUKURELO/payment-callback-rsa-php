<?php

declare(strict_types=1);

namespace Tests;

use App\Controllers\PaymentController;
use App\Repositories\PaymentRepository;
use App\Services\IdempotencyService;
use App\Services\PaymentService;
use App\Services\SignatureService;
use PHPUnit\Framework\TestCase;

final class PaymentControllerTest extends TestCase
{
    private string $privateKeyPem;
    private string $publicKeyPath;

    protected function setUp(): void
    {
        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        self::assertNotFalse($key, 'Unable to generate RSA key pair for test.');
        self::assertTrue(openssl_pkey_export($key, $privateKeyPem), 'Unable to export private key.');

        $details = openssl_pkey_get_details($key);
        self::assertIsArray($details, 'Unable to read public key details.');
        self::assertArrayHasKey('key', $details);

        $publicKeyPath = tempnam(sys_get_temp_dir(), 'pub_');
        self::assertNotFalse($publicKeyPath, 'Unable to create temp file for public key.');
        self::assertNotFalse(file_put_contents($publicKeyPath, (string) $details['key']));

        $this->privateKeyPem = $privateKeyPem;
        $this->publicKeyPath = $publicKeyPath;
    }

    protected function tearDown(): void
    {
        if (is_file($this->publicKeyPath)) {
            unlink($this->publicKeyPath);
        }
    }

    public function testHandleReturnsSourceIpInSuccessResponse(): void
    {
        $payload = [
            'success' => 'true',
            'action' => 'authorize',
            'merchant_code' => 'b0deb6f3-e51a-48a7-9268-f1441d46f7bd',
            'merchant_operation_number' => '2391645',
            'transaction' => [
                'transaction_id' => '5hk8rwa3h3cq9oyfs3a28v1ms',
                'state' => 'AUTORIZADO',
                'amount' => '15000',
                'currency' => '604',
                'processor_response' => [
                    'date' => '17-01-2024 12:27:46',
                    'authorization_code' => '055552',
                ],
            ],
            'meta' => [
                'status' => [
                    'code' => '00',
                    'message_ilgn' => [
                        [
                            'locale' => 'es_PE',
                            'value' => 'Procesado correctamente',
                        ],
                    ],
                ],
            ],
        ];

        $rawPayload = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = $this->sign($rawPayload);
        $controller = $this->buildController();

        $response = $controller->handle($rawPayload, ['signature' => $signature], '203.0.113.24');

        self::assertSame(200, $response['statusCode']);
        self::assertSame('Callback processed successfully.', $response['body']['message']);
        self::assertSame('203.0.113.24', $response['body']['received_from_ip']);
        self::assertSame('203.0.113.24', $response['body']['data']['received_from_ip']);
        self::assertSame('2391645', $response['body']['data']['merchant_operation_number']);
    }

    public function testHandleReturnsSourceIpInValidationResponse(): void
    {
        $controller = $this->buildController();

        $response = $controller->handle('{}', [], '198.51.100.10');

        self::assertSame(400, $response['statusCode']);
        self::assertSame('Missing signature header.', $response['body']['message']);
        self::assertSame('198.51.100.10', $response['body']['received_from_ip']);
    }

    private function buildController(): PaymentController
    {
        $paymentRepository = new PaymentRepository();
        $idempotencyService = new IdempotencyService($paymentRepository);
        $paymentService = new PaymentService($paymentRepository, $idempotencyService);
        $signatureService = new SignatureService($this->publicKeyPath);

        return new PaymentController($signatureService, $paymentService);
    }

    private function sign(string $payload): string
    {
        $privateKey = openssl_pkey_get_private($this->privateKeyPem);
        self::assertNotFalse($privateKey, 'Unable to load private key.');

        $signed = openssl_sign($payload, $signature, $privateKey, OPENSSL_ALGO_SHA512);
        if (is_object($privateKey)) {
            openssl_free_key($privateKey);
        }

        self::assertTrue($signed, 'Unable to sign payload.');

        return base64_encode($signature);
    }
}
