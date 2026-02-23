# SentinelPay

> **High-Availability Distributed Payment API Simulation**
> Built on Laravel 12 · PostgreSQL · Redis · RabbitMQ · Docker

A production-grade, headless payment API demonstrating:
- **ACID-compliant** fund transfers with PostgreSQL
- **Pessimistic row-level locking** (`SELECT FOR UPDATE`) to eliminate race conditions
- **Redis idempotency** to prevent double-charging on retries
- **HMAC-SHA256 request signing** to prevent payload tampering
- **Immutable append-only ledger** enforced at app + DB trigger level
- **RabbitMQ** queue integration for async job dispatch

---

## Architecture

```
Client → Nginx → Laravel App ──→ PostgreSQL (accounts, transactions, ledgers)
                      │────────→ Redis       (idempotency cache, session)
                      └────────→ RabbitMQ    (async job queue)
```

### Core Data Model

| Table          | Key Columns                                                                 | Notes                                      |
|----------------|-----------------------------------------------------------------------------|--------------------------------------------|
| `accounts`     | `id` UUID, `user_id` UUID, `balance` DECIMAL(20,2), `currency`             | DB-level `CHECK (balance >= 0)`            |
| `transactions` | `id` UUID, `idempotency_key` UNIQUE, `sender_id`, `receiver_id`, `status`, `signature` | `CHECK (amount > 0)` |
| `ledgers`      | `id` BIGINT, `account_id`, `transaction_id`, `type` ENUM(debit/credit), `amount`, `balance_after` | **Append-only** — PG triggers prevent UPDATE/DELETE |

---

## Security: HMAC-SHA256 Signing

Every mutating request (`POST /api/v1/transfers`) must include an `X-Signature` header:

```bash
BODY='{"sender_account_id":"...","receiver_account_id":"...","amount":"100.00","currency":"USD","idempotency_key":"unique-key-here"}'
SIG=$(echo -n "$BODY" | openssl dgst -sha256 -hmac "your-hmac-secret" | awk '{print $2}')

curl -X POST http://localhost:8080/api/v1/transfers \
  -H "Content-Type: application/json" \
  -H "X-Signature: $SIG" \
  -d "$BODY"
```

The middleware uses `hash_equals()` for **timing-safe comparison** to prevent timing attacks.

---

## Concurrency: Pessimistic Locking

```php
// TransferService.php — core locking pattern
DB::transaction(function () use ($senderAccountId, $receiverAccountId) {
    // Sort UUIDs to prevent DEADLOCKS between concurrent transfers of the same pair
    $lockIds = [$senderAccountId, $receiverAccountId];
    sort($lockIds);

    // SELECT ... FOR UPDATE — exclusive row lock until transaction commits
    $accounts = Account::whereIn('id', $lockIds)
        ->lockForUpdate()
        ->get()
        ->keyBy('id');

    // ... debit sender, credit receiver, write ledger entries
});
```

**Why sorted UUIDs?** If Transfer A locks `[alice → bob]` and Transfer B locks `[bob → alice]` concurrently without ordering, they deadlock. Sorting guarantees both always acquire locks in the same order.

---

## Idempotency (Redis)

```
Client sends idempotency_key: "pay-order-42-retry-3"
    │
    ├─ Redis GET "idempotency:pay-order-42-retry-3"
    │       ├─ HIT  → return cached transaction (no DB write)
    │       └─ MISS → proceed with transfer → cache result for 24h
```

Safe for network retries, client crashes, and duplicate webhook delivery.

---

## Append-Only Ledger

The `ledgers` table records every balance change. It is protected at **two levels**:

1. **Application level** — `Ledger::update()` and `Ledger::delete()` throw `RuntimeException`.
2. **Database level** — PostgreSQL triggers `BEFORE UPDATE` and `BEFORE DELETE` raise an exception.

```
Ledger entry structure (per transfer):
  [debit]  sender_id   | amount=100 | balance_after=900
  [credit] receiver_id | amount=100 | balance_after=600
```

---

## Docker Quick Start

### Prerequisites
- Docker Desktop (Windows/macOS/Linux)
- `openssl` in PATH for signing requests

### 1. Clone & configure

```bash
git clone <repo-url> sentinelpay
cd sentinelpay
copy .env.example .env        # Windows
# cp .env.example .env        # macOS/Linux
```

Edit `.env` and set a real `HMAC_SECRET`:
```
HMAC_SECRET=my-super-secret-32-char-minimum-key
```

### 2. Start the stack

```bash
docker compose up -d --build
```

Services started:
| Service    | URL / Port                          |
|------------|-------------------------------------|
| API        | http://localhost:8080               |
| PostgreSQL | localhost:5432                      |
| Redis      | localhost:6379                      |
| RabbitMQ   | localhost:5672 · UI: localhost:15672|

### 3. Run migrations & seed

```bash
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed
```

The seeder creates:
- `alice@sentinelpay.io` → Account with **$10,000**
- `bob@sentinelpay.io` → Account with **$5,000**

---

## API Reference

### Health Check
```
GET /api/v1/health
```

### Execute Transfer
```
POST /api/v1/transfers
Header: X-Signature: <hmac_sha256>
Header: Content-Type: application/json

{
  "sender_account_id":   "uuid",
  "receiver_account_id": "uuid",
  "amount":              "100.00",
  "currency":            "USD",
  "idempotency_key":     "unique-string-min-16-chars"
}
```

**Responses:**
| Status | Meaning                        |
|--------|-------------------------------|
| 201    | Transfer completed             |
| 401    | Missing X-Signature            |
| 403    | Invalid signature / inactive account |
| 404    | Account not found              |
| 422    | Insufficient funds / validation error |

### Account Balance
```
GET /api/v1/accounts/{uuid}/balance
```

### Account Transactions
```
GET /api/v1/accounts/{uuid}/transactions?per_page=20
```

---

## Audit Command

Verifies that every account's balance equals `SUM(credits) - SUM(debits)` from the ledger, proving financial integrity:

```bash
# Audit all accounts
docker compose exec app php artisan audit:ledger

# Audit a specific account
docker compose exec app php artisan audit:ledger --account=<uuid>
```

Sample output:
```
╔══════════════════════════════════════════════╗
║          SentinelPay Ledger Audit            ║
╚══════════════════════════════════════════════╝

 Account ID    | Account Balance | Net Ledger Sum | Debit Sum | Credit Sum | Status
 3a1b2c3d...   | 9900.00         | 9900.00        | 100.00    | 0.00       | ✓ PASS
 7e8f9a0b...   | 5100.00         | 5100.00        | 0.00      | 100.00     | ✓ PASS

Passed: 2
Failed: 0

✅ All accounts passed financial integrity check. Ledger is consistent.
```

---

## Testing

Tests run against a real PostgreSQL instance (SQLite cannot enforce `SELECT FOR UPDATE` semantics).

### Setup test database
```bash
# Inside the Docker container
psql -U sentinelpay -c "CREATE DATABASE sentinelpay_test;"
```

### Run all tests
```bash
docker compose exec app php artisan test
# or
docker compose exec app ./vendor/bin/pest
```

### Race condition test highlights

The `Race Condition — Pessimistic Locking` test suite:
1. Creates a sender with **$1,000** and receiver with **$0**
2. Fires **10 sequential transfers** of **$150** each (simulating concurrent clients)
3. Asserts the final state:
   - `sender.balance + receiver.balance == $1,000` (money conservation)
   - `sender.balance >= 0` (no overdraft)
   - Exactly `successCount × 2` ledger entries exist
   - 0 transactions stuck in `pending` or `processing`

---

## Project Structure

```
app/
├── Console/Commands/
│   └── AuditLedgerCommand.php      # audit:ledger
├── Exceptions/
│   ├── AccountInactiveException.php
│   ├── AccountNotFoundException.php
│   └── InsufficientFundsException.php
├── Http/
│   ├── Controllers/Api/
│   │   └── TransactionController.php
│   ├── Middleware/
│   │   └── VerifyHmacSignature.php  # HMAC-SHA256 validation
│   └── Requests/
│       └── TransferRequest.php      # Validation + idempotency key
├── Models/
│   ├── Account.php
│   ├── Ledger.php                   # Append-only enforced
│   ├── Transaction.php
│   └── User.php
└── Services/
    └── TransferService.php          # Core locking + idempotency logic

database/
├── migrations/
│   ├── 2024_01_01_000001_create_users_table.php
│   ├── 2024_01_01_000002_create_accounts_table.php
│   ├── 2024_01_01_000003_create_transactions_table.php
│   └── 2024_01_01_000004_create_ledgers_table.php  # PG triggers included

docker/
├── app/Dockerfile                   # PHP 8.3-FPM + pgsql + redis ext
├── app/php.ini
└── nginx/default.conf

tests/Feature/
└── TransferTest.php                 # Race condition + HMAC + ledger tests
```

---

## Environment Variables

| Variable          | Default         | Description                              |
|-------------------|-----------------|------------------------------------------|
| `DB_CONNECTION`   | `pgsql`         | Must be PostgreSQL                       |
| `REDIS_HOST`      | `redis`         | Redis hostname                           |
| `REDIS_PASSWORD`  | `secret`        | Redis auth password                      |
| `RABBITMQ_HOST`   | `rabbitmq`      | RabbitMQ hostname                        |
| `RABBITMQ_VHOST`  | `sentinelpay`   | RabbitMQ virtual host                    |
| `HMAC_SECRET`     | *(required)*    | **Must be set** — used for request signing |
| `QUEUE_CONNECTION`| `rabbitmq`      | Queue backend driver                     |

---

## License

MIT © SentinelPay
