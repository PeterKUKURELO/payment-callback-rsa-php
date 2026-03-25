<?php

declare(strict_types=1);

namespace Tests;

use App\Services\SignatureService;
use PHPUnit\Framework\TestCase;

final class SignatureServiceTest extends TestCase
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

    public function testVerifyReturnsTrueForValidSignature(): void
    {
        $payload = '{"success":"true","merchant_operation_number":"2391645"}';
        $signature = $this->sign($payload);
        $service = new SignatureService($this->publicKeyPath);

        self::assertTrue($service->verify($payload, $signature));
    }

    public function testVerifyReturnsFalseForTamperedPayload(): void
    {
        $originalPayload = '{"success":"true","merchant_operation_number":"2391645"}';
        $tamperedPayload = '{"success":"false","merchant_operation_number":"2391645"}';
        $signature = $this->sign($originalPayload);
        $service = new SignatureService($this->publicKeyPath);

        self::assertFalse($service->verify($tamperedPayload, $signature));
    }

    public function testVerifyReturnsFalseForInvalidBase64Signature(): void
    {
        $payload = '{"success":"true","merchant_operation_number":"2391645"}';
        $service = new SignatureService($this->publicKeyPath);

        self::assertFalse($service->verify($payload, 'not-base64***'));
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
