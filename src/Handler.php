<?php

declare(strict_types=1);

use App\Controllers\PaymentController;
use App\Repositories\PaymentRepository;
use App\Services\IdempotencyService;
use App\Services\PaymentService;
use App\Services\RequestIpResolver;
use App\Services\SignatureService;

require dirname(__DIR__) . '/vendor/autoload.php';

$config = require __DIR__ . '/Config/config.php';

$paymentRepository = new PaymentRepository();
$idempotencyService = new IdempotencyService($paymentRepository);
$paymentService = new PaymentService($paymentRepository, $idempotencyService);
$signatureService = new SignatureService((string) $config['public_key_path']);
$requestIpResolver = new RequestIpResolver();
$controller = new PaymentController($signatureService, $paymentService);

return static function (array $event) use ($controller, $requestIpResolver): array {
    $rawBody = (string) ($event['body'] ?? '');
    $headers = is_array($event['headers'] ?? null) ? $event['headers'] : [];
    $sourceIp = $requestIpResolver->fromLambdaEvent($event);

    if (($event['isBase64Encoded'] ?? false) === true) {
        $decodedBody = base64_decode($rawBody, true);
        if ($decodedBody === false) {
            $body = ['message' => 'Invalid base64 body.'];
            if ($sourceIp !== null) {
                $body['received_from_ip'] = $sourceIp;
            }

            return [
                'statusCode' => 400,
                'headers' => ['Content-Type' => 'application/json'],
                'body' => json_encode($body, JSON_UNESCAPED_UNICODE),
            ];
        }

        $rawBody = $decodedBody;
    }

    $response = $controller->handle($rawBody, $headers, $sourceIp);
    $encodedBody = json_encode($response['body'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    return [
        'statusCode' => $response['statusCode'],
        'headers' => ['Content-Type' => 'application/json'],
        'body' => $encodedBody !== false ? $encodedBody : '{"message":"Response encoding error."}',
    ];
};
