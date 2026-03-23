<?php

declare(strict_types=1);

use App\Controllers\PaymentController;
use App\Repositories\PaymentRepository;
use App\Services\IdempotencyService;
use App\Services\PaymentService;
use App\Services\SignatureService;

require dirname(__DIR__) . '/vendor/autoload.php';

$config = require dirname(__DIR__) . '/src/Config/config.php';

$paymentRepository = new PaymentRepository();
$idempotencyService = new IdempotencyService($paymentRepository);
$paymentService = new PaymentService($paymentRepository, $idempotencyService);
$signatureService = new SignatureService((string) $config['public_key_path']);
$controller = new PaymentController($signatureService, $paymentService);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($path !== '/callback' || $method !== 'POST') {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['message' => 'Not found'], JSON_UNESCAPED_UNICODE);
    exit;
}

$rawBody = file_get_contents('php://input');
$rawBody = $rawBody === false ? '' : $rawBody;

$headers = function_exists('getallheaders') ? getallheaders() : [];
if (!is_array($headers) || $headers === []) {
    $headers = [];
    foreach ($_SERVER as $name => $value) {
        if (!str_starts_with($name, 'HTTP_')) {
            continue;
        }

        $headerName = strtolower(str_replace('_', '-', substr($name, 5)));
        $headers[$headerName] = (string) $value;
    }
}

$response = $controller->handle($rawBody, $headers);

http_response_code($response['statusCode']);
header('Content-Type: application/json');
echo json_encode($response['body'], JSON_UNESCAPED_UNICODE);
