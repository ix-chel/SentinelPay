# SentinelPay Deployment & Operations Guide

This guide covers setup, environment configuration, database migration, and operation of SentinelPay.

## Prerequisites
- **PHP**: 8.2+ with extensions: `pdo_pgsql`, `bcmath`, `openssl`, `mbstring`
- **Package Managers**: Composer 2.x, Node.js 18+ & npm
- **Database**: PostgreSQL 15+ (local native or cloud provider e.g. Supabase)

---

## 1. Local Native Development Setup

1. **Clone repository and install dependencies:**
   ```powershell
   composer install
   npm install
   ```

2. **Environment configuration:**
   ```powershell
   Copy-Item .env.example .env
   php artisan key:generate
   ```

3. **Configure Database Connection in `.env`:**
   ```ini
   DB_CONNECTION=pgsql
   DB_HOST=aws-0-xx.pooler.supabase.com
   DB_PORT=5432
   DB_DATABASE=postgres
   DB_USERNAME=postgres.your-project-id
   DB_PASSWORD=your-secure-password
   DB_SSLMODE=require

   # Cryptographic Secret for Payment Signatures
   HMAC_SECRET=your-32-character-crypto-secret

   # Local Cache and Queue Drivers
   CACHE_STORE=file
   SESSION_DRIVER=file
   QUEUE_CONNECTION=sync
   ```

4. **Run Database Migrations & Seeders:**
   ```powershell
   php artisan migrate
   php artisan db:seed
   ```

5. **Compile Frontend & Start Servers:**
   - Terminal 1 (Vite Dev Server):
     ```powershell
     npm run dev
     ```
   - Terminal 2 (Laravel API Server):
     ```powershell
     php artisan serve
     ```

6. **Access:**
   - Dashboard UI: `http://localhost:8000`
   - Health Probe: `http://localhost:8000/api/v1/health`

---

## 2. Production Deployment & Containerization

### Critical Environment Variables
| Variable | Production Value | Note |
|---|---|---|
| `APP_ENV` | `production` | Disables debug traces |
| `APP_DEBUG` | `false` | Prevents sensitive data leakage |
| `DB_CONNECTION` | `pgsql` | Required for PostgreSQL ACID locking & triggers |
| `CACHE_STORE` | `redis` | Multi-node distributed idempotency cache |
| `QUEUE_CONNECTION` | `rabbitmq` | Asynchronous worker message broker |
| `HMAC_SECRET` | *(64-hex random string)* | Request signing master secret |

### Build Production Frontend Assets
```bash
npm run build
```
Compiled assets and manifest are placed into `public/build/`.

### Run Production Migrations
```bash
php artisan migrate --force
```

---

## 3. Operational Integrity & Audit Tooling

### Ledger Reconciliation Audit
Run the built-in ledger audit tool to mathematically verify that all account balances match the cumulative sum of immutable ledger entries:
```bash
php artisan audit:ledger
```

To automatically reconcile any drifted balances to match the ledger source of truth:
```bash
php artisan audit:ledger --fix
```
