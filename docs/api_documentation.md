# SentinelPay API Documentation

This document describes the REST API endpoints available in SentinelPay. 

## Base URL
All API requests should be prefixed with `/api/v1`.

## Authentication & Security
All mutating endpoints must include an HMAC-SHA256 signature to prevent man-in-the-middle tampering.
- **Header**: `X-Signature: <hex_encoded_hmac>`
- **Secret**: The shared `HMAC_SECRET` symmetric key.
- **Algorithm**: `hash_hmac('sha256', $rawRequestBody, $secret)`

Read-only endpoints (e.g., balance checking) utilize a Sanctum token via the Authorization header (`Bearer <token>`).

---

## Endpoints

### 1. Execute a Transfer
Creates a new transfer between two active accounts in the same currency.

**Endpoint:** `POST /transfers`

**Headers:**
- `Content-Type: application/json`
- `X-Signature: <hmac_sha256>`

**Request Body:**
```json
{
  "sender_account_id": "9a3b-...",
  "receiver_account_id": "1f8c-...",
  "amount": "150.00",
  "currency": "USD",
  "idempotency_key": "unique-request-id-12345"
}
```

**Responses:**
- `201 Created`: Transfer successful.
- `401 Unauthorized`: Missing `X-Signature`.
- `403 Forbidden`: Invalid signature.
- `422 Unprocessable Entity`: Insufficient funds or validation rules failed.

---

### 2. Check Account Balance
Retrieve the current available balance for an account.

**Endpoint:** `GET /accounts/{account_id}/balance`

**Headers:**
- `Authorization: Bearer <sanctum_token>`

**Responses:**
- `200 OK`: 
```json
{
  "status": "success",
  "data": {
    "account_id": "9a3b-...",
    "balance": "1500.00",
    "currency": "USD",
    "is_active": true
  }
}
```
- `403 Forbidden`: The account does not belong to the authenticated user.

---

### 3. Get Account Transactions
Retrieve a paginated list of sent and received transactions for an account.

**Endpoint:** `GET /accounts/{account_id}/transactions?per_page=20`

**Headers:**
- `Authorization: Bearer <sanctum_token>`

**Responses:**
- `200 OK`: returns a paginated Laravel JSON response of `Transaction` models.
