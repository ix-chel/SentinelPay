# SentinelPay MongoDB Migration Walkthrough

This document outlines the changes and features implemented to migrate SentinelPay to MongoDB and establish it as a production-ready, CV-worthy project.

## 1. Architecture Changes
- Switched the primary data store from PostgreSQL to **MongoDB**.
- Updated [docker-compose.yml](file:///c:/Users/USER/Documents/ALL-HEAVY-PROJECT-CODE/SentinelPay/docker-compose.yml), [config/database.php](file:///c:/Users/USER/Documents/ALL-HEAVY-PROJECT-CODE/SentinelPay/config/database.php), and [.env.example](file:///c:/Users/USER/Documents/ALL-HEAVY-PROJECT-CODE/SentinelPay/.env.example) to run a MongoDB instance.
- Integrated `mongodb/laravel-mongodb` package for seamless Eloquent ORM compatibility.
- Implemented MongoDB specific index migrations including unique indexes (e.g. `hashed_key`) and TTL indexes (e.g. `expires_at` for idempotency caching).

## 2. Core Enhancements

### API Key Security Model
- Implemented API key generation returning a cryptographically secure plain-text key (only once) while storing a `hash_sha256` digest securely.
- Enforced modular API key scoping (`*`, `transfers:write`, `accounts:read`) and per-merchant rate limiting via custom [VerifyApiKey](file:///c:/Users/USER/Documents/ALL-HEAVY-PROJECT-CODE/SentinelPay/app/Http/Middleware/VerifyApiKey.php#11-52) middleware.

### Transfers API & Consistency (Ledger-First)
- Designed an immutable ledger-first `transfers` implementation. 
- Integrated **MongoDB Atomic Updates** (`$inc`) for decrementing and incrementing [accounts](file:///c:/Users/USER/Documents/ALL-HEAVY-PROJECT-CODE/SentinelPay/app/Models/Merchant.php#17-21) balances robustly using the `TransferController@transfer` endpoint.
- Adapted [AuditLedgerCommand.php](file:///c:/Users/USER/Documents/ALL-HEAVY-PROJECT-CODE/SentinelPay/app/Console/Commands/AuditLedgerCommand.php) to leverage MongoDB's aggregation framework (`$match` & `$group`) to reconcile ledger entries with account balances.

### Idempotency Logic
- Safely handling `Idempotency-Key` headers on transfers.
- Responses cached in the explicit `idempotency_keys` collection mapped to `TTL` automatic expiration (24h default) ensuring fault-tolerant retries.

### Webhook Eventing
- Async delivery of external events (e.g. `transfer.succeeded`) to multiple registered endpoints.
- Payloads signed symmetrically (`Stripe-Signature` style) using HMAC-SHA256 implemented in [WebhookService](file:///c:/Users/USER/Documents/ALL-HEAVY-PROJECT-CODE/SentinelPay/app/Services/WebhookService.php#8-30).
- Background delivery processing handled by [DispatchWebhook](file:///c:/Users/USER/Documents/ALL-HEAVY-PROJECT-CODE/SentinelPay/app/Jobs/DispatchWebhook.php#15-72) Laravel Jobs with robust exponential backoff.

### Minimal CV Dashboard UI
- Added a lightweight, dependency-free Tailwind CSS CDN dashboard inside `resources/views/dashboard/`.
- Accessible at `http://localhost:8080/dashboard`, exposing the backend logic (Merchants, API Keys, Transfers, Webhook Configurations, and delivery Logs) easily to interviewers or reviewers.

## 3. Product-Grade Signals Added
- **GitHub Actions (CI)**: Configured tests inside `.github/workflows/ci.yml`.
- **API Documentation**: Automated OpenAPI/Swagger documentation generated at `http://localhost:8080/docs/api` using `Scramble`.
- **Demo Data Seeding**: Fully configured `DatabaseSeeder.php` resets and dynamically provisions a functional `Acme Corp` merchant and API keys natively via `php artisan db:seed`.
- **Postman Collection**: Created a testing request collection inside the repository `docs/` folder.
- **README Updates**: Transformed the repository overview into an enterprise-focused narrative demonstrating high-level architectural decisions and system operations.

## Validation & Results
- Codebase cleanly synthesizes and builds routes dynamically (`php artisan route:list`).
- All controllers, models, and migrations conform to MongoDB paradigms gracefully.
