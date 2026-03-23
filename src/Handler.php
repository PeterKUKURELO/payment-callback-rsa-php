<?php

declare(strict_types=1);

use App\Controllers\PaymentController;
use App\Repositories\PaymentRepository;
use App\Services\IdempotencyService;
use App\Services\PaymentService;
use App\Services\SignatureService;

require dirname(__DIR__) . '/vendor/autoload.php';

$config = require __DIR__ . '/Config/config.php';

$paymentRepository = new PaymentRepository();
$idempotencyService = new IdempotencyService($paymentRepository);
$paymentService = new PaymentService($paymentRepository, $idempotencyService);
$signatureService = new SignatureService((string) $config['public_key_path']);
$controller = new PaymentController($signatureService, $paymentService);

return static function (array $event) use ($controller): array {
    $rawBody = (string) ($event['body'] ?? '');

    if (($event['isBase64Encoded'] ?? false) === true) {
        $decodedBody = base64_decode($rawBody, true);
        if ($decodedBody === false) {
            return [
                'statusCode' => 400,
                'headers' => ['Content-Type' => 'application/json'],
                'body' => json_encode(['message' => 'Invalid base64 body.'], JSON_UNESCAPED_UNICODE),
            ];
        }

        $rawBody = $decodedBody;
    }

    $headers = is_array($event['headers'] ?? null) ? $event['headers'] : [];
    $response = $controller->handle($rawBody, $headers);
    $encodedBody = json_encode($response['body'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    return [
        'statusCode' => $response['statusCode'],
        'headers' => ['Content-Type' => 'application/json'],
        'body' => $encodedBody !== false ? $encodedBody : '{"message":"Response encoding error."}',
    ];
};
