# S2S Callback en PHP 8.2 (AWS Lambda + Bref)

Proyecto para procesar callbacks de pagos Server-to-Server validando firma `SHA512withRSA` (`openssl_verify`) sin modificar el JSON RAW antes de validar.

## Estructura

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
tests/
scripts/
  deploy.sh
  deploy.ps1
examples/
  callback.json
composer.json
serverless.yml
.env.example
```

## Requisitos

- PHP 8.2
- Composer
- Serverless Framework
- Credenciales AWS configuradas

## Configuracion

1. Copia variables de entorno:

```bash
cp .env.example .env
```

2. Reemplaza `src/Config/public.pem` con la llave publica real de la pasarela.
3. Configura AWS credentials (`aws configure`) en la maquina donde haras el deploy.

## Instalacion

```bash
composer install
```

## Prueba local

```bash
composer serve
```

Endpoint local:

```text
POST http://127.0.0.1:8000/callback
```

## Ejemplo de JSON de callback

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

## Ejemplo de firma y request

Firma del body RAW (sin formatear ni alterar):

```bash
cp examples/callback.json payload.json
openssl dgst -sha512 -sign private.pem -binary payload.json | openssl base64 -A > signature.txt
```

Enviar callback:

```bash
curl -X POST "http://127.0.0.1:8000/callback" \
  -H "Content-Type: application/json" \
  -H "signature: $(cat signature.txt)" \
  --data-binary @payload.json
```

## Deploy a AWS Lambda

```bash
composer install --no-dev -o
npx serverless deploy
```

Endpoint desplegado:

```text
POST /callback
```

## Deploy rapido (recomendado)

Linux/CentOS:

```bash
chmod +x scripts/deploy.sh
./scripts/deploy.sh preprod us-east-1
```

Windows/PowerShell:

```powershell
.\scripts\deploy.ps1 -Stage preprod -Region us-east-1
```

## Ver endpoint despues del deploy

```bash
npx serverless info --stage preprod --region us-east-1
```

Busca la URL base del HTTP API y agrega `/callback`.
