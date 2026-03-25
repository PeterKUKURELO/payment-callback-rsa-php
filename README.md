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
POST http://127.0.0.1:7071/callback
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
curl -X POST "http://127.0.0.1:7071/callback" \
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

# 🌐 Deploy en VPS (Nginx + DuckDNS + HTTPS)

También es posible desplegar el endpoint sin AWS Lambda, utilizando un servidor VPS con Nginx como reverse proxy.

## Arquitectura


Internet (Pay-me)
↓
https://paymentcallback.duckdns.org/callback

↓
Nginx (80/443)
↓
PHP Server (127.0.0.1:7071)
↓
Aplicación (PaymentController)


---

## Requisitos adicionales

- Servidor Linux (CentOS recomendado)
- Nginx
- Certbot (Let's Encrypt)
- Dominio gratuito (DuckDNS)

---

## Configuración de dominio (DuckDNS)

1. Crear dominio en: https://www.duckdns.org  
   Ejemplo:


paymentcallback.duckdns.org


2. Configurar actualización automática:

```bash
mkdir -p /root/duckdns
nano /root/duckdns/update.sh
echo url="https://www.duckdns.org/update?domains=paymentcallback&token=TU_TOKEN&ip=" | curl -k -o /root/duckdns/duck.log -K -

Permisos:

chmod 700 /root/duckdns/update.sh

Probar:

bash /root/duckdns/update.sh
cat /root/duckdns/duck.log

Debe responder:

OK
Configuración de Nginx

Crear archivo:

nano /etc/nginx/conf.d/payment-callback.conf

Contenido:

server {
    listen 80;
    server_name paymentcallback.duckdns.org;

    location /callback {
        proxy_pass http://127.0.0.1:7071/callback;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}

Validar y reiniciar:

nginx -t
systemctl restart nginx
Levantar servidor PHP
php -S 127.0.0.1:7071 -t public

O en background:

nohup php -S 127.0.0.1:7071 -t public > /tmp/payment-callback.log 2>&1 &

Verificar:

ss -lntp | grep 7071
Configurar HTTPS (Let's Encrypt)
dnf install certbot python3-certbot-nginx -y
certbot --nginx -d paymentcallback.duckdns.org

Resultado:

https://paymentcallback.duckdns.org/callback
Endpoint final
POST https://paymentcallback.duckdns.org/callback
Verificación
curl -X POST "https://paymentcallback.duckdns.org/callback" \
  -H "Content-Type: application/json" \
  -H "signature: test" \
  --data '{}'

Respuesta esperada:

{"message":"Missing signature header."}
Logs
Nginx (requests)
tail -f /var/log/nginx/access.log

Ejemplo:

POST /callback HTTP/1.1" 200
Nginx (errores)
tail -f /var/log/nginx/error.log
Aplicación PHP
tail -f /tmp/payment-callback.log
Debug de payload (opcional)

Agregar en PaymentController.php:

file_put_contents('/tmp/callback_debug.log', file_get_contents('php://input') . PHP_EOL, FILE_APPEND);

Ver:

tail -f /tmp/callback_debug.log
Notas importantes
El endpoint debe ser HTTPS obligatorio para producción.
No se debe modificar el JSON antes de validar la firma.
Usar --data-binary en pruebas para preservar el RAW body.
No es necesario private.pem en producción (solo public.pem).
Estado del despliegue
✔ Dominio activo (DuckDNS)
✔ Nginx configurado
✔ HTTPS activo (Let's Encrypt)
✔ Backend funcionando
✔ Endpoint público operativo
✔ Recepción de callbacks validada
