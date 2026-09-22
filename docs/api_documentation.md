# SentinelPay API Documentation (v1)

This document provides the complete, authoritative specification for all REST API endpoints in SentinelPay.

## Base URL
All API requests must be prefixed with `/api/v1`.

## Security & Authentication Architecture

SentinelPay employs a dual-tier authentication strategy:
1. **Mutating Payment Execution (`POST /transfers`)**:
   - Requires an `X-Signature` header calculated using HMAC-SHA256 over the raw JSON payload with `HMAC_SECRET`.
   - Timing-safe verification is enforced via `hash_equals()`.
   - If called by an authenticated user with a Sanctum Bearer token, sender account ownership is verified.
2. **Account Introspection & Session (`/accounts/*`, `/auth/*`)**:
   - Protected via Laravel Sanctum (`Authorization: Bearer <sanctum_token>`).
   - Only the authenticated account owner may inspect balances or transactions. Horizontal privilege escalation returns `403 Forbidden`.
3. **Public / Monitoring**:
   - `GET /health` is public for uptime monitoring and probes.

---

## Rate Limiting Policy

| Endpoint | Method | Rate Limit | Protection Objective |
|---|---|---|---|
| `/auth/login` | `POST` | 5 req / min | Brute-force & credential stuffing prevention |
| `/auth/logout` | `POST` | 60 req / min | Session cleanup throttling |
| `/transfers` | `POST` | 30 req / min | Financial flooding & wallet draining defense |
| `/accounts` | `GET` | 60 req / min | Account enumeration throttling |
| `/accounts/{id}/balance` | `GET` | 60 req / min | Polling defense |
| `/accounts/{id}/transactions` | `GET` | 60 req / min | Query flood defense |
| `/health` | `GET` | 120 req / min | Uptime monitoring |

---

## API Endpoints

### 1. Health Probe
- **Endpoint**: `GET /api/v1/health`
- **Headers**: None
- **Response `200 OK`**:
```json
{
  "status": "ok",
  "service": "SentinelPay",
  "timestamp": "2026-09-22T17:31:35+00:00"
}
```

---

### 2. User Login
Authenticate and obtain a Sanctum Bearer token.
- **Endpoint**: `POST /api/v1/auth/login`
- **Headers**: `Content-Type: application/json`
- **Request Body**:
```json
{
  "email": "operator@sentinelpay.io",
  "password": "SecretPassword123"
}
```
- **Response `200 OK`**:
```json
{
  "status": "success",
  "message": "Authenticated successfully.",
  "data": {
    "token": "1|8f921...plainTextToken",
    "token_type": "Bearer",
    "user": {
      "id": "01a0c9f0-9252-71b9-a27c-d47a4eabbe34",
      "name": "Operator",
      "email": "operator@sentinelpay.io",
      "accounts": [
        {
          "id": "01a0c9f0-9252-71b9-a27c-d47a4eabbe34",
          "balance": "10000.00",
          "currency": "USD",
          "is_active": true
        }
      ]
    }
  }
}
```
- **Error Responses**:
  - `401 Unauthorized`: `"The provided credentials are incorrect."`
  - `422 Unprocessable Entity`: Validation failed on email or password.

---

### 3. User Logout
Revokes the current Sanctum token.
- **Endpoint**: `POST /api/v1/auth/logout`
- **Headers**: `Authorization: Bearer <sanctum_token>`
- **Response `200 OK`**:
```json
{
  "status": "success",
  "message": "Token revoked. You have been logged out."
}
```

---

### 4. List User Accounts
Returns all accounts owned by the authenticated user.
- **Endpoint**: `GET /api/v1/accounts`
- **Headers**: `Authorization: Bearer <sanctum_token>`
- **Response `200 OK`**:
```json
{
  "status": "success",
  "data": [
    {
      "id": "01a0c9f0-9252-71b9-a27c-d47a4eabbe34",
      "balance": "10000.00",
      "currency": "USD",
      "is_active": true,
      "created_at": "2026-09-22T00:00:00.000000Z"
    }
  ]
}
```

---

### 5. Check Account Balance
Retrieve current available balance for an account. Requires ownership.
- **Endpoint**: `GET /api/v1/accounts/{account_id}/balance`
- **Headers**: `Authorization: Bearer <sanctum_token>`
- **Response `200 OK`**:
```json
{
  "status": "success",
  "data": {
    "account_id": "01a0c9f0-9252-71b9-a27c-d47a4eabbe34",
    "balance": "10000.00",
    "currency": "USD",
    "is_active": true
  }
}
```
- **Error Responses**:
  - `403 Forbidden`: Account does not belong to authenticated user (`"FORBIDDEN"`).
  - `404 Not Found`: Account does not exist.

---

### 6. Get Account Transactions
Retrieve paginated transaction history for an account. Requires ownership.
- **Endpoint**: `GET /api/v1/accounts/{account_id}/transactions?per_page=20`
- **Headers**: `Authorization: Bearer <sanctum_token>`
- **Response `200 OK`**:
```json
{
  "status": "success",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": "3a1e204c-...",
        "idempotency_key": "4f8a12e9-...",
        "sender_id": "01a0c9f0-...",
        "receiver_id": "02b1d8e1-...",
        "amount": "150.00",
        "currency": "USD",
        "status": "completed",
        "created_at": "2026-09-22T17:35:00.000000Z"
      }
    ],
    "per_page": 20,
    "total": 1
  }
}
```

---

### 7. Execute Fund Transfer
Initiate an ACID fund transfer with row-level locking and idempotency guarantees.
- **Endpoint**: `POST /api/v1/transfers`
- **Headers**:
  - `Content-Type: application/json`
  - `X-Signature: <hmac_sha256_hex>`
  - `Authorization: Bearer <sanctum_token>` *(optional for M2M; mandatory if enforcing user account boundary)*
- **Request Body**:
```json
{
  "sender_account_id": "01a0c9f0-9252-71b9-a27c-d47a4eabbe34",
  "receiver_account_id": "02b1d8e1-4567-89ab-cdef-0123456789ab",
  "amount": "150.00",
  "currency": "USD",
  "idempotency_key": "unique-request-uuid-here"
}
```
- **Response `201 Created`**:
```json
{
  "status": "success",
  "message": "Transfer completed successfully.",
  "data": {
    "transaction_id": "5e1b2390-...",
    "idempotency_key": "unique-request-uuid-here",
    "sender_id": "01a0c9f0-9252-71b9-a27c-d47a4eabbe34",
    "receiver_id": "02b1d8e1-4567-89ab-cdef-0123456789ab",
    "amount": "150.00",
    "currency": "USD",
    "status": "completed",
    "created_at": "2026-09-22T17:35:00.000000Z"
  }
}
```
- **Error Responses**:
  - `401 Unauthorized`: Missing `X-Signature` header.
  - `403 Forbidden`: Invalid HMAC signature, or account inactive, or user does not own sender account.
  - `404 Not Found`: Account ID not found.
  - `409 Conflict`: Idempotency key reused with a different payload.
    ```json
    {
      "status": "error",
      "error": "IDEMPOTENCY_CONFLICT",
      "message": "The idempotency key is already associated with a different request."
    }
    ```
  - `422 Unprocessable Entity`: Insufficient funds, self-transfer, or invalid format.
