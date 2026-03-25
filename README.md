# Payment Callback RSA PHP

![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?logo=php&logoColor=white)
![AWS Lambda](https://img.shields.io/badge/AWS%20Lambda-Bref-orange)
![CI](https://img.shields.io/github/actions/workflow/status/PeterKUKURELO/payment-callback-rsa-php/ci.yml?branch=main)
![Status](https://img.shields.io/badge/status-production--oriented-success)

Callback handler S2S en PHP 8.2 para notificaciones de pagos, con validación de firma `SHA512withRSA`, preservación del payload RAW e idempotencia en el procesamiento.

## Qué demuestra este proyecto

Este repositorio está pensado como pieza de portafolio backend enfocada en integraciones de pago y despliegue real. Muestra:

- validación criptográfica con `openssl_verify`
- protección de integridad sobre el body RAW
- separación clara de responsabilidades (`Controller -> Services -> Repository`)
- soporte para despliegue en AWS Lambda y VPS
- documentación técnica, pruebas y CI básica

## Arquitectura

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

Documentación adicional:

- [docs/architecture.md](docs/architecture.md)
- [docs/flow.md](docs/flow.md)

## Stack

- PHP 8.2
- OpenSSL
- PHPUnit
- Bref
- AWS Lambda
- Serverless Framework
- Nginx
- Composer

## Estructura del proyecto

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

## Flujo de validación

1. Recibir el callback HTTP.
2. Leer el body RAW sin modificarlo.
3. Extraer el header `signature`.
4. Verificar la firma RSA con `OPENSSL_ALGO_SHA512`.
5. Decodificar el JSON solo después de que la firma sea válida.
6. Validar campos de negocio.
7. Aplicar idempotencia usando `merchant_operation_number`.
8. Persistir el resultado del procesamiento.

## Requisitos

- PHP 8.2
- Composer
- Serverless Framework
- Credenciales AWS configuradas para despliegue

## Instalación

```bash
composer install
cp .env.example .env
```

Luego:

1. Reemplaza `src/Config/public.pem` por la llave pública real de la pasarela.
2. Configura AWS si vas a desplegar en Lambda:

```bash
aws configure
```

## Ejecución local

```bash
composer serve
```

Endpoint local:

```text
POST http://127.0.0.1:7071/callback
```

## Payload de ejemplo

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

## Generación de firma para pruebas

```bash
cp examples/callback.json payload.json
openssl dgst -sha512 -sign private.pem -binary payload.json | openssl base64 -A > signature.txt
```

## Request de prueba

```bash
curl -X POST "http://127.0.0.1:7071/callback" \
  -H "Content-Type: application/json" \
  -H "signature: $(cat signature.txt)" \
  --data-binary @payload.json
```

## Deploy en AWS Lambda

```bash
composer install --no-dev -o
npx serverless deploy
```

Ver endpoint:

```bash
npx serverless info --stage preprod --region us-east-1
```

Deploy rápido:

Linux

```bash
chmod +x scripts/deploy.sh
./scripts/deploy.sh preprod us-east-1
```

Windows

```powershell
.\scripts\deploy.ps1 -Stage preprod -Region us-east-1
```

## Deploy en VPS con Nginx + HTTPS

Arquitectura:

```text
Internet
   |
   v
https://tu-dominio/callback
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

Configuración base de Nginx:

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

Levantar servidor local:

```bash
php -S 127.0.0.1:7071 -t public
```

En background:

```bash
nohup php -S 127.0.0.1:7071 -t public > /tmp/payment-callback.log 2>&1 &
```

Activar HTTPS:

```bash
dnf install certbot python3-certbot-nginx -y
certbot --nginx -d paymentcallback.duckdns.org
```

Endpoint final:

```text
POST https://paymentcallback.duckdns.org/callback
```

## Verificación rápida

```bash
curl -X POST "https://paymentcallback.duckdns.org/callback" \
  -H "Content-Type: application/json" \
  -H "signature: test" \
  --data '{}'
```

Respuesta esperada:

```json
{"message":"Invalid signature."}
```

## Tests

```bash
composer test
```

Cobertura actual:

- validación correcta de firma RSA
- rechazo de firma inválida
- rechazo de firma con Base64 malformado

## CI

GitHub Actions ejecuta:

- `composer install`
- lint de PHP
- PHPUnit

Workflow:

- `.github/workflows/ci.yml`

## Notas de producción

- No modificar el JSON RAW antes de validar la firma.
- Usar `--data-binary` en pruebas para preservar integridad.
- No exponer llaves privadas.
- Obligar HTTPS en producción.
- La implementación actual de `PaymentRepository` es en memoria; para producción real debe reemplazarse por DynamoDB, RDS, Redis u otra persistencia compartida.

## Estado

- [x] Validación RSA `SHA512withRSA`
- [x] Integridad del RAW body
- [x] Idempotencia
- [x] Deploy en AWS Lambda
- [x] Deploy en VPS
- [x] Pruebas básicas
- [x] CI básica

## Autor

Peter Kukurelo  
Backend Developer | Payment Integrations
