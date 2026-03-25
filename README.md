# Payment Callback RSA PHP

![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?logo=php&logoColor=white)
![Serverless](https://img.shields.io/badge/Serverless-AWS-FD5750?logo=serverless&logoColor=white)
![Status](https://img.shields.io/badge/status-production--oriented-2EA44F)

Production-oriented PHP 8.2 callback handler for payment gateway notifications, validating `SHA512withRSA` signatures with `openssl_verify`, preserving raw JSON integrity, and applying idempotent processing.

## Overview

This project processes Server-to-Server (S2S) payment callbacks securely by:

- validating RSA signatures with `SHA512withRSA`
- preserving the raw payload exactly as received before verification
- preventing duplicate processing through idempotency checks
- supporting both AWS Lambda (Serverless + Bref) and VPS deployments

## Features

- RSA signature validation using OpenSSL
- raw payload verification with no JSON mutation
- idempotent callback handling
- clean architecture (`Controller -> Services -> Repository`)
- local testing with PHP built-in server
- AWS Lambda deployment with Bref
- VPS deployment behind Nginx + HTTPS
- basic CI with lint + PHPUnit

## Architecture

```text
Payment Gateway
        |
        v
PaymentController
        |
        +--> SignatureService
        |
        +--> PaymentService
                |
                +--> IdempotencyService
                |
                +--> PaymentRepository
```

Detailed documentation:

- [docs/architecture.md](docs/architecture.md)
- [docs/flow.md](docs/flow.md)

## Tech Stack

- PHP 8.2
- OpenSSL
- PHPUnit
- AWS Lambda + Bref
- Serverless Framework
- Nginx
- Composer

## Project Structure

```text
src/
  Controllers/PaymentController.php
  Services/SignatureService.php
  Services/PaymentService.php
  Services/IdempotencyService.php
  Repositories/PaymentRepository.php
  Config/public.pem
  Config/config.php
  Handler.php

public/
  index.php

docs/
  architecture.md
  flow.md

tests/
  SignatureServiceTest.php

scripts/
  deploy.sh
  deploy.ps1

examples/
  callback.json

.github/
  workflows/ci.yml

composer.json
serverless.yml
.env.example
```

## Requirements

- PHP 8.2
- Composer
- Serverless Framework
- AWS credentials configured for Lambda deployments

## Installation

```bash
composer install
```

## Configuration

```bash
cp .env.example .env
```

Then:

1. Replace `src/Config/public.pem` with the real payment gateway public key.
2. Configure AWS credentials when deploying to Lambda:

```bash
aws configure
```

## Running Locally

```bash
composer serve
```

Endpoint:

```text
POST http://127.0.0.1:7071/callback
```

## Callback Validation Flow

1. Receive HTTP request.
2. Read the raw body from `php://input`.
3. Extract the `signature` header.
4. Validate the signature with the RSA public key.
5. Decode and validate the JSON payload.
6. Check idempotency using `merchant_operation_number`.
7. Store or update the transaction result.

See the full flow in [docs/flow.md](docs/flow.md).

## Example Payload

```json
{
  "success": "true",
  "action": "authorize",
  "merchant_code": "b0deb6f3-e51a-48a7-9268-f1441d46f7bd",
  "merchant_operation_number": "2391645",
  "transaction": {
    "transaction_id": "5hk8rwa3h3cq9oyfs3a28v1ms",
    "state": "AUTORIZADO",
    "amount": "15000",
    "currency": "604",
    "processor_response": {
      "date": "17-01-2024 12:27:46",
      "authorization_code": "055552",
      "result_message": {
        "code": "00",
        "description": "Approval and completed successfully"
      }
    }
  },
  "meta": {
    "status": {
      "code": "00",
      "message_ilgn": [
        {
          "locale": "es_PE",
          "value": "Procesado correctamente"
        }
      ]
    }
  }
}
```

## Signature Generation For Testing

```bash
cp examples/callback.json payload.json
openssl dgst -sha512 -sign private.pem -binary payload.json | openssl base64 -A > signature.txt
```

## Test Request

```bash
curl -X POST "http://127.0.0.1:7071/callback" \
  -H "Content-Type: application/json" \
  -H "signature: $(cat signature.txt)" \
  --data-binary @payload.json
```

## Deployment On AWS Lambda

```bash
composer install --no-dev -o
npx serverless deploy
```

Get the deployed endpoint:

```bash
npx serverless info --stage preprod --region us-east-1
```

## Quick Deploy

Linux:

```bash
chmod +x scripts/deploy.sh
./scripts/deploy.sh preprod us-east-1
```

Windows:

```powershell
.\scripts\deploy.ps1 -Stage preprod -Region us-east-1
```

## VPS Deployment (Nginx + HTTPS)

### Architecture

```text
Internet (Payment Gateway)
        |
        v
https://your-domain/callback
        |
        v
Nginx
        |
        v
PHP Server (127.0.0.1:7071)
        |
        v
Application
```

### Requirements

- Linux server
- Nginx
- Certbot
- Domain or subdomain

### Nginx Configuration

```nginx
server {
    listen 80;
    server_name paymentcallback.duckdns.org;

    location /callback {
        proxy_pass http://127.0.0.1:7071/callback;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    }
}
```

### Start PHP Server

```bash
php -S 127.0.0.1:7071 -t public
```

Background mode:

```bash
nohup php -S 127.0.0.1:7071 -t public > /tmp/payment-callback.log 2>&1 &
```

### Enable HTTPS

```bash
dnf install certbot python3-certbot-nginx -y
certbot --nginx -d paymentcallback.duckdns.org
```

Final endpoint:

```text
POST https://paymentcallback.duckdns.org/callback
```

## Verification

```bash
curl -X POST "https://paymentcallback.duckdns.org/callback" \
  -H "Content-Type: application/json" \
  -H "signature: test" \
  --data '{}'
```

Expected response:

```json
{"message":"Invalid signature."}
```

## Logs

```bash
tail -f /var/log/nginx/access.log
tail -f /var/log/nginx/error.log
tail -f /tmp/payment-callback.log
```

## Debug (Optional)

```php
file_put_contents('/tmp/callback_debug.log', file_get_contents('php://input') . PHP_EOL, FILE_APPEND);
```

## Security Notes

- Do not modify raw JSON before signature validation.
- Always use HTTPS in production.
- Never expose private keys.
- Use `--data-binary` to preserve payload integrity.

## Idempotency Strategy

- Unique `merchant_operation_number`
- prevent duplicate processing
- safe retries from the gateway

Current repository storage is in-memory and useful for local/demo flows. For real production idempotency across processes or cold starts, replace `PaymentRepository` with a persistent backend such as DynamoDB, RDS, or Redis.

## Tests

Run tests locally:

```bash
composer test
```

Current automated coverage includes:

- `SignatureService` happy path verification
- invalid signature rejection
- malformed Base64 rejection

## CI

GitHub Actions runs:

- `composer install`
- PHP lint
- PHPUnit tests

Workflow file:

- [`.github/workflows/ci.yml`](.github/workflows/ci.yml)

## Status

- [x] RSA signature validation
- [x] Raw payload integrity
- [x] Idempotent processing
- [x] AWS deployment
- [x] VPS deployment
- [x] Basic test coverage
- [x] Basic CI pipeline

## Author

Peter Kukurelo  
Backend Developer | Payment Integrations
