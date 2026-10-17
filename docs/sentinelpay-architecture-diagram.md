# SentinelPay — Architecture Diagram

This diagram is based on the current repository routes, controllers, transfer service, models, and architecture notes.

> **Frontend note:** The current tree contains `resources/views/welcome.blade.php` and `resources/js/sentinelpay.js` for the dashboard UI. The README also describes React/Vite as a target/frontend foundation, so confirm that migration status before treating React as the deployed UI.

## 1. Runtime architecture

```mermaid
flowchart TB
  subgraph CLIENT["CLIENT / OPERATIONS CONSOLE"]
    BROWSER["Operator browser"]
    DASH["Dashboard UI\nBlade view + JavaScript entry"]
    BROWSER --> DASH
  end

  subgraph API["LARAVEL 12 API"]
    ROUTES["API routes\n/api/v1/*"]
    HEALTH["GET /health"]
    LOGIN["POST /auth/login\nrate limited"]
    LOGOUT["POST /auth/logout\nSanctum + rate limit"]
    READ["Account read endpoints\n/accounts, balance, transactions"]
    SANCTUM["Laravel Sanctum\nBearer token + account ownership checks"]
    TRANSFER["POST /transfers"]
    HMAC["VerifyHmacSignature\nX-Signature · HMAC-SHA256"]
    THROTTLE["Transfer rate limit\n30 requests/minute"]
    VALIDATE["TransferRequest\nValidated request payload"]
    CONTROLLER["TransactionController"]
    SERVICE["TransferService\nAtomic transfer orchestration"]

    ROUTES --> HEALTH
    ROUTES --> LOGIN
    ROUTES --> LOGOUT
    LOGOUT --> SANCTUM
    ROUTES --> READ --> SANCTUM
    ROUTES --> TRANSFER --> HMAC --> THROTTLE --> VALIDATE --> CONTROLLER --> SERVICE
  end

  subgraph IDEMPOTENCY["IDEMPOTENCY LAYER"]
    CACHE["Cache accelerator\nFile/array locally; Redis for clustered deployment"]
    KEY["Canonical payload fingerprint\nSHA-256"]
    UNIQUE["PostgreSQL unique idempotency key\nAuthoritative source of truth"]
  end

  subgraph DB["SUPABASE POSTGRESQL"]
    ACC["accounts\nBalance constraint; active state; currency"]
    TX["transactions\nUnique idempotency_key + payload_hash"]
    LEDGER["ledgers\nDebit/credit audit trail\nAppend-only trigger protection"]
  end

  subgraph JOBS["POST-TRANSFER NOTIFICATION"]
    JOB["TransactionNotificationJob"]
    BROKER["Queue driver\nSync locally/tests; RabbitMQ configured for async deployment"]
    JOB --> BROKER
  end

  DASH -->|"HTTP / JSON"| ROUTES
  SERVICE --> KEY
  SERVICE <--> CACHE
  SERVICE --> UNIQUE
  UNIQUE --> TX
  SERVICE -->|"SELECT FOR UPDATE\nUUIDs sorted before lock"| ACC
  SERVICE -->|"same DB transaction"| TX
  SERVICE -->|"debit + credit entries"| LEDGER
  SERVICE -->|"dispatch after commit"| JOB

  classDef client fill:#e8f2ff,stroke:#2563eb,color:#172554
  classDef api fill:#eef2ff,stroke:#4f46e5,color:#1e1b4b
  classDef security fill:#fff7ed,stroke:#ea580c,color:#7c2d12
  classDef data fill:#ecfdf5,stroke:#059669,color:#064e3b
  classDef jobs fill:#f5f3ff,stroke:#7c3aed,color:#4c1d95
  class BROWSER,DASH client
  class ROUTES,HEALTH,LOGIN,LOGOUT,READ,SANCTUM,TRANSFER,THROTTLE,VALIDATE,CONTROLLER,SERVICE api
  class HMAC,CACHE,KEY,UNIQUE security
  class ACC,TX,LEDGER data
  class JOB,BROKER jobs
```

## 2. Transfer lifecycle and integrity controls

```mermaid
flowchart TD
  START["Client submits POST /api/v1/transfers"] --> SIGN["Verify X-Signature\nHMAC-SHA256 + hash_equals"]
  SIGN --> SIGOK{"Signature valid?"}
  SIGOK -- "No" --> REJECT["Reject request\nMissing: 401 · Invalid: 403"]
  SIGOK -- "Yes" --> RATE["Apply transfer rate limit"]
  RATE --> VALID["TransferRequest validates fields"]
  VALID --> IDEM["Calculate canonical payload hash\nCheck cache, then PostgreSQL"]
  IDEM --> EXIST{"Idempotency key exists?"}
  EXIST -- "Same payload" --> REPLAY["Return existing transaction\nNo second debit"]
  EXIST -- "Different payload" --> CONFLICT["HTTP 409\nIdempotency conflict"]
  EXIST -- "No" --> BEGIN["Begin database transaction"]
  BEGIN --> LOCK["Lock sender + receiver rows\nSELECT FOR UPDATE in sorted UUID order"]
  LOCK --> CHECK{"Accounts active, currencies match,\nfunds sufficient?"}
  CHECK -- "No" --> ROLLBACK["Rollback transaction\nReturn domain error"]
  CHECK -- "Yes" --> CREATE["Create processing transaction record"]
  CREATE --> DEBIT["Debit sender balance\nAppend DEBIT ledger entry"]
  DEBIT --> CREDIT["Credit receiver balance\nAppend CREDIT ledger entry"]
  CREDIT --> COMPLETE["Mark transaction completed"]
  COMPLETE --> COMMIT["Commit atomically"]
  COMMIT --> CACHE["Warm idempotency cache"]
  CACHE --> NOTIFY["Dispatch TransactionNotificationJob"]
  NOTIFY --> SUCCESS["Return HTTP 201"]
  
  DBGUARD["Database guarantees:\nunique idempotency key · balance constraint\nledger UPDATE/DELETE blocked by trigger"]
  DBGUARD -. "enforces invariants" .-> BEGIN
  DBGUARD -. "protects records" .-> DEBIT

  classDef ok fill:#ecfdf5,stroke:#059669,color:#064e3b
  classDef reject fill:#fff1f2,stroke:#e11d48,color:#881337
  classDef process fill:#eff6ff,stroke:#2563eb,color:#1e3a8a
  class START,SIGN,RATE,VALID,IDEM,BEGIN,LOCK,CREATE,DEBIT,CREDIT,COMPLETE,COMMIT,CACHE,NOTIFY process
  class REPLAY,SUCCESS ok
  class REJECT,CONFLICT,ROLLBACK reject
```

## Implementation references

- API routes: [`routes/api.php`](../routes/api.php)
- HMAC verification: [`app/Http/Middleware/VerifyHmacSignature.php`](../app/Http/Middleware/VerifyHmacSignature.php)
- Request validation and API response handling: [`app/Http/Controllers/Api/TransactionController.php`](../app/Http/Controllers/Api/TransactionController.php), [`app/Http/Requests/TransferRequest.php`](../app/Http/Requests/TransferRequest.php)
- Transfer transaction, row locks, idempotency, and job dispatch: [`app/Services/TransferService.php`](../app/Services/TransferService.php)
- Full engineering notes: [`docs/architecture_docs.md`](architecture_docs.md)
