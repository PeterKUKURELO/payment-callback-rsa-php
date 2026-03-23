<?php

declare(strict_types=1);

namespace App\Services;

final class SignatureService
{
    public function __construct(
        private readonly string $publicKeyPath
    ) {
    }

    public function verify(string $body, string $signatureBase64): bool
    {
        $signature = base64_decode($signatureBase64, true);
        if ($signature === false) {
            error_log('Invalid Base64 signature.');
            return false;
        }

        $publicKeyContent = @file_get_contents($this->publicKeyPath);
        if ($publicKeyContent === false) {
            error_log(sprintf('Unable to read public key file: %s', $this->publicKeyPath));
            return false;
        }

        $publicKey = openssl_pkey_get_public($publicKeyContent);
        if ($publicKey === false) {
            error_log('Invalid public key format.');
            $this->logOpenSslErrors();
            return false;
        }

        $verified = openssl_verify($body, $signature, $publicKey, OPENSSL_ALGO_SHA512);
        openssl_free_key($publicKey);

        if ($verified === 1) {
            return true;
        }

        if ($verified === 0) {
            error_log('Signature verification failed.');
            return false;
        }

        error_log('OpenSSL verification error.');
        $this->logOpenSslErrors();

        return false;
    }

    private function logOpenSslErrors(): void
    {
        while (($error = openssl_error_string()) !== false) {
            error_log(sprintf('OpenSSL: %s', $error));
        }
    }
}
