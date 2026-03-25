# Callback Flow

## End-to-End Flow

```mermaid
sequenceDiagram
    participant PG as Payment Gateway
    participant APP as Callback App
    participant SIG as SignatureService
    participant PAY as PaymentService
    participant IDEM as IdempotencyService
    participant REPO as PaymentRepository

    PG->>APP: POST /callback + raw JSON + signature header
    APP->>SIG: verify(rawBody, signature)
    SIG-->>APP: valid / invalid
    APP->>APP: decode JSON only after signature passes
    APP->>PAY: process(payload)
    PAY->>IDEM: isDuplicate(merchant_operation_number)
    IDEM->>REPO: exists(operationNumber)
    REPO-->>IDEM: true / false
    IDEM-->>PAY: duplicate? 
    alt duplicate callback
        PAY-->>APP: processed=true, duplicate=true
    else first callback
        PAY->>REPO: save(record)
        PAY-->>APP: processed=true, duplicate=false
    end
    APP-->>PG: JSON response
```

## Validation Rules

1. Read the request body without mutating whitespace, ordering, or formatting.
2. Extract the `signature` header case-insensitively.
3. Base64-decode the signature.
4. Verify with `openssl_verify(..., OPENSSL_ALGO_SHA512)`.
5. Decode JSON only after the signature is valid.
6. Validate required business fields.
7. Check idempotency using `merchant_operation_number`.
8. Persist the transaction outcome.

## Failure Modes

- Missing signature: return `400`.
- Invalid Base64 signature: return `400`.
- Signature mismatch: return `400`.
- Malformed JSON: return `400`.
- Invalid business fields: return `400`.
- Unexpected runtime error: return `500`.
