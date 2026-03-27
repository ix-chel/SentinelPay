# SentinelPay

SentinelPay is a Laravel 12 backend prototype for merchant-owned account transfers. The strongest part of this project is transaction correctness under concurrency: row-level locking, idempotency handling, paired ledger entries, and queued webhook delivery.

This is not a finished payment platform, not a PSP, and not something that should be presented as production-ready without caveats. It is better described as a solid technical prototype with a few well-implemented core ideas and several areas that still need hardening.

## What This Project Actually Does Well

- Executes transfers inside a PostgreSQL transaction.
- Locks both accounts with deterministic ordering plus `SELECT ... FOR UPDATE` to reduce double-spend and deadlock risk.
- Persists paired debit and credit ledger entries for each successful transfer.
- Replays duplicate requests via merchant-scoped idempotency keys instead of charging twice.
- Supports merchant API keys with hashed storage and simple per-key rate limiting.
- Requires HMAC request signatures for transfer creation.
- Dispatches webhook deliveries asynchronously through RabbitMQ-backed queues.
- Includes an `audit:ledger` command to compare stored account balances against the latest ledger balance.
- Ships with a small Blade dashboard for local inspection and demo purposes.

## Honest Project Status

> "SentinelPay is a backend-focused transfer system prototype. The transfer path is reasonably thought through, but the surrounding platform concerns are still incomplete."

What is still missing or immature:

- This is not a complete payment lifecycle. There are no refunds, reversals, chargebacks, disputes, settlement flows, or external banking rails.
- The ledger is append-only by application behavior, but database-level immutability protections are not implemented yet.
- API key scopes exist in the data model and middleware, but route-level scope enforcement is not actually used today.
- The authentication story is inconsistent. API key authentication is active, while Sanctum-related code and older docs still exist but are not part of the active route flow.
- Operational maturity is limited: no structured observability stack, no dead-letter strategy, no alerting, no tracing, and no serious incident tooling.
- Webhook delivery is basic retry logic, not a full delivery platform.
- The dashboard is a demo/debugging UI, not a hardened admin console.
- Some files under `docs/` describe behavior that no longer matches the current implementation and should be treated as draft documentation.
- There is no claim here around PCI, fraud detection, KYC, secret rotation, backup policy, or compliance readiness.

## Architecture Summary

Current stack:

- Laravel 12
- PostgreSQL 17
- Redis 7
- RabbitMQ 3
- Nginx
- Docker Compose

Core flow:

1. Merchant sends a signed transfer request with `X-API-Key`, `Idempotency-Key`, and `X-Signature`.
2. The API validates the key, rate limit, and HMAC signature.
3. The transfer service acquires an idempotency lock, opens a database transaction, and locks both accounts.
4. Balances are updated, the transfer is stored, and paired ledger rows are inserted.
5. The response is persisted for idempotent replay.
6. A webhook job is queued after commit.

## API Surface Available Today

Current routes implemented in `routes/api.php`:

- `GET /api/v1/health`
- `POST /api/v1/merchants`
- `POST /api/v1/keys`
- `DELETE /api/v1/keys/{id}`
- `POST /api/v1/transfers`
- `GET /api/v1/transfers/{id}`
- `GET /api/v1/balances`
- `GET /api/v1/ledger/{accountId}`
- `GET /api/v1/webhooks`
- `POST /api/v1/webhooks`
- `DELETE /api/v1/webhooks/{id}`

Anything beyond that should be treated as planned work, not current capability.

## Local Development

### Prerequisites

- Docker Desktop
- Docker Compose

### Bootstrapping

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app php artisan key:generate --no-interaction
docker compose exec app php artisan migrate:fresh --seed --force
```

Local endpoints:

- App: `http://localhost:8080`
- Dashboard: `http://localhost:8080/dashboard`
- RabbitMQ UI: `http://localhost:15672`

The default seeder creates:

- Merchant: `Acme Corp`
- Demo API key: `sp_live_demo1234567890`

That seeded key is for local development only. It must never be treated as a real credential pattern for production.

## Running Tests

Run the focused test container:

```bash
docker compose run --rm --profile test test --compact
```

Useful targeted runs:

```bash
docker compose run --rm --profile test test tests/Feature/TransferTest.php --compact
docker compose run --rm --profile test test tests/Feature/AuditLedgerCommandTest.php --compact
```

## Why This Repo Is Still Worth Reviewing

Even with the gaps above, there is real engineering value here:

- The transfer path prioritizes correctness over superficial CRUD completeness.
- The project shows awareness of idempotency, balance drift, and concurrent mutation risk.
- The codebase is small enough to evolve without a full rewrite.

The right expectation is not "production payment system." The right expectation is "promising foundation for a payment-system-style backend exercise."
