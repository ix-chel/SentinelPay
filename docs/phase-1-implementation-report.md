# Phase 1 Implementation Report — Transaction Correctness, Integrity & Regression Hardening

**Platform**: SentinelPay 2.0  
**Phase**: 1  
**Status**: COMPLETE WITH KNOWN LIMITATIONS  
**Author**: Senior Backend Engineering  
**Date**: September 22, 2026  

---

## 1. Executive Summary

Phase 1 implemented critical transaction correctness, database integrity, idempotency verification, rollback atomicity, and regression coverage for the SentinelPay financial platform.

Key achievements:
- **Idempotency Payload Fingerprinting**: Resolved silent transaction reuse on payload mismatch by calculating a deterministic SHA-256 canonical hash across all financially relevant fields (`sender_id`, `receiver_id`, `amount`, `currency`). Reusing an idempotency key with a different payload now safely returns `HTTP 409 Conflict`.
- **Same-Key Multi-Process Concurrency**: Added a true parallel OS-level concurrency test (`proc_open`) where 10 processes race simultaneously with identical idempotency keys and payloads, proving PostgreSQL unique constraint race handling and ensuring exactly one financial effect.
- **Mid-Transaction Rollback & Atomicity**: Added a deterministic, test-scoped failure injection test verifying that when an exception occurs after partial writes have executed, PostgreSQL completely rolls back account balances, transactions, and ledger rows.
- **Direct PostgreSQL Constraint & Trigger Verification**: Added integration tests directly exercising database CHECK constraints (`accounts_balance_non_negative`, `transactions_amount_positive`, `ledgers_amount_positive`, `ledgers_balance_after_non_negative`) and triggers (`ledger_no_update`, `ledger_no_delete`).
- **Sanctum Authentication & Token Bug Fix**: Discovered and resolved a schema bug in `personal_access_tokens` where integer morphs (`$table->morphs('tokenable')`) prevented UUID users from authenticating. Added complete test coverage for login, logout, and token revocation.
- **Account & Transfer Authorization Boundaries**: Added tests proving account ownership boundaries on balance and transaction history endpoints. Added sender ownership verification when authenticated user sessions call `POST /api/v1/transfers`.
- **Zero Regressions**: All 16 original baseline tests pass intact. The test suite expanded from 16 tests (59 assertions) to 49 tests (178 assertions).

---

## 2. Verified Audit Findings

The audit findings were verified against the actual repository code prior to modification:

| Audit Finding | Status | Verification Detail |
|---|---|---|
| **Finding A — Idempotency Payload Mismatch** | **CONFIRMED** | `TransferService.php` returned cached or DB transaction rows solely by `idempotency_key`, with no inspection of whether amount, currency, sender, or receiver matched. |
| **Finding B — Same-Key Concurrent Race** | **CONFIRMED** | `tests/Concurrent/ConcurrencyTest.php` generated unique UUID idempotency keys for each process; no test existed for identical keys racing concurrently. |
| **Finding C — Mid-Transaction Rollback** | **CONFIRMED** | The existing test in `TransferTest.php` failed validation *prior* to database writes (`bccomp` check threw before `Transaction::create`). No test proved atomicity after partial database mutations. |
| **Finding D — Database Constraints** | **CONFIRMED** | PostgreSQL CHECK constraints and append-only triggers were not tested directly via SQL; tests relied solely on application-layer guards. |
| **Finding E — Transaction Lifecycle** | **CONFIRMED** | State transition from `processing` to `completed` occurs inside a single database transaction. Upon failure, the database rolls back the row entirely, leaving no persisted `processing` or `failed` records. |
| **Finding F — Authentication / Authorization** | **CONFIRMED** | Zero feature tests existed for login, logout, or account ownership checks. Furthermore, personal access tokens used bigint morphs incompatible with UUID users. |

---

## 3. Workstream 1: Idempotency Payload Fingerprinting

### Deterministic Fingerprint Design & Canonicalization
A canonical representation is constructed using all financially relevant parameters:
1. `amount`: Normalized to 2 decimal places via `bcadd($amount, '0', 2)`.
2. `currency`: Trimmed and converted to uppercase (`strtoupper(trim($currency))`).
3. `sender_id`: Account UUID.
4. `receiver_id`: Account UUID.

The payload array is sorted by keys alphabetically (`ksort`) and encoded to strict JSON before hashing with SHA-256:
```php
$payload = [
    'amount'      => bcadd($amount, '0', 2),
    'currency'    => strtoupper(trim($currency)),
    'receiver_id' => $receiverAccountId,
    'sender_id'   => $senderAccountId,
];
ksort($payload);
$canonicalString = json_encode($payload, JSON_THROW_ON_ERROR);
$payloadHash = hash('sha256', $canonicalString);
```

### Storage & Safe Migration
- Migration: `2026_09_22_000001_add_payload_hash_to_transactions_table.php`
- Adds nullable `payload_hash VARCHAR(64)` to `transactions`.
- Safely backfills existing rows without data loss.
- In `app/Models/Transaction.php`, `payload_hash` is included in `$fillable`.

### Conflict Handling
When an existing idempotency key is encountered (via fast-path Cache, direct DB query, or TOCTOU unique constraint race resolution):
- **Matching Hash**: Returns the existing transaction record without duplicate processing.
- **Mismatch Hash**: Throws `App\Exceptions\IdempotencyConflictException`.
- **API Response**: `TransactionController` catches the exception and returns `HTTP 409 Conflict`:
  ```json
  {
    "status": "error",
    "error": "IDEMPOTENCY_CONFLICT",
    "message": "The idempotency key is already associated with a different request."
  }
  ```

### Tests Added
- Same key + different amount $\rightarrow$ throws `IdempotencyConflictException` / returns HTTP 409.
- Same key + different sender $\rightarrow$ rejected.
- Same key + different receiver $\rightarrow$ rejected.
- Same key + different currency $\rightarrow$ rejected.
- Same key + altered payload $\rightarrow$ leaves original transaction and balances completely intact.
- Semantically equivalent amounts (`100`, `100.00`, `100.0`) $\rightarrow$ produce identical fingerprints.

---

## 4. Workstream 2: Same-Key Concurrency

### Scenario
A true multi-process test was added to `tests/Concurrent/ConcurrencyTest.php` using `proc_open` and `sentinelpay:test-transfer`:
- 10 concurrent OS-level PHP processes.
- Connected simultaneously to PostgreSQL (`sentinelpay_test`).
- Executing identical sender, receiver, amount ($150.00), currency (USD), and the **exact same idempotency key**.

### Actual Test Results
- **Financial effect**: Exactly 1 transfer ($150.00 debited from sender, $150.00 credited to receiver).
- **Sender balance**: $1,000.00 $\rightarrow$ $850.00.
- **Receiver balance**: $0.00 $\rightarrow$ $150.00.
- **PostgreSQL transactions**: Exactly 1 transaction row created in `transactions`.
- **PostgreSQL ledgers**: Exactly 2 ledger entries created (1 debit, 1 credit).
- **Process results**: All 10 processes resolved with exit code 0 and returned the identical `transaction_id`.
- **Errors/Crashes**: 0.

---

## 5. Workstream 3: Mid-Transaction Failure Injection & Rollback

### Injection Seam
To avoid production-only failure flags, the test uses a strictly test-scoped Eloquent lifecycle listener on `Ledger::creating`.
The hook triggers an exception only when saving the credit ledger entry:
```php
$listener = function (Ledger $ledger) {
    if ($ledger->type === Ledger::TYPE_CREDIT) {
        throw new \RuntimeException('Injected controlled mid-transaction failure');
    }
};
Ledger::creating($listener);
```
At this point in `TransferService::transfer`:
1. `Transaction::create` has executed and inserted a row.
2. Sender balance has been debited and `$sender->save()` has executed.
3. Debit `Ledger::create` has executed.
4. Credit `Ledger::create` throws `RuntimeException`.

In a `finally` block, the listener is explicitly removed from the dispatcher (`$dispatcher->forget(...)`) to prevent side effects on subsequent tests.

### Assertions After Rollback
- Sender balance remains $1,000.00 (unchanged).
- Receiver balance remains $500.00 (unchanged).
- Transaction record is completely rolled back (count = 0).
- Ledger records are completely rolled back (count = 0).
- Cache key was never set.

---

## 6. Workstream 4 & 5: PostgreSQL Integrity & Trigger Verification

Direct SQL operations were executed against `sentinelpay_test` within isolated transactions/savepoints, verifying PostgreSQL-level enforcement:

1. **`accounts_balance_non_negative`**: Direct `UPDATE accounts SET balance = -50.00` rejected with `QueryException` referencing constraint name.
2. **`transactions_amount_positive`**: Direct `INSERT INTO transactions` with negative amount (-25.00) and zero amount (0.00) rejected with `QueryException` referencing constraint name.
3. **`ledgers_amount_positive`**: Direct `INSERT INTO ledgers` with negative amount (-10.00) rejected with `QueryException`.
4. **`ledgers_balance_after_non_negative`**: Direct `INSERT INTO ledgers` with negative balance after (-10.00) rejected with `QueryException`.
5. **`ledger_no_update` Trigger**: Direct SQL `UPDATE ledgers SET amount = 999.00` rejected with `QueryException` containing `"Ledger is append-only. UPDATE and DELETE operations are not permitted."`.
6. **`ledger_no_delete` Trigger**: Direct SQL `DELETE FROM ledgers` rejected with `QueryException` containing `"Ledger is append-only. UPDATE and DELETE operations are not permitted."`.

After every expected database exception, the failed transaction/savepoint was rolled back, leaving the PostgreSQL connection clean for subsequent queries.

---

## 7. Workstream 6: Transaction State Integrity

- **Terminal Completed State**: Successful transfers commit with `status = completed`, non-null `payload_hash`, matching timestamps, and exactly 2 ledger entries.
- **Rollback on Failure**: Transfers failing validation (insufficient funds, inactive account, invalid currency) or runtime errors roll back atomically. The database does not retain phantom, orphaned, or non-terminal (`processing`/`pending`) records.
- **State Model Preservation**: Did not introduce artificial persistent `failed` or `reversed` states, preserving the existing atomic rollback architecture.

---

## 8. Workstream 7 & 8: Authentication & Authorization

### Authentication Suite (`tests/Feature/AuthTest.php`)
- `POST /api/v1/auth/login` (valid credentials) $\rightarrow$ 200 OK, Bearer token, user info.
- `POST /api/v1/auth/login` (invalid password) $\rightarrow$ 401 Unauthorized.
- `POST /api/v1/auth/login` (non-existent email) $\rightarrow$ 401 Unauthorized.
- `POST /api/v1/auth/login` (missing parameters) $\rightarrow$ 422 Unprocessable Entity.
- `POST /api/v1/auth/logout` (valid token) $\rightarrow$ 200 OK, token deleted.
- `POST /api/v1/auth/logout` (unauthenticated) $\rightarrow$ 401 Unauthorized.
- Revoked token access to protected endpoints $\rightarrow$ 401 Unauthorized.

### Personal Access Tokens Bug Fix
In `database/migrations/2026_03_11_042143_create_personal_access_tokens_table.php`, replaced `$table->morphs('tokenable')` with `$table->uuidMorphs('tokenable')`. Because `User` models use UUID primary keys, the default bigint morph column caused PostgreSQL syntax errors on every token insert.

### Account & Transfer Authorization Suite (`tests/Feature/AccountAuthorizationTest.php`)
- User A inspecting User A balance $\rightarrow$ 200 OK.
- User A inspecting User B balance $\rightarrow$ 403 Forbidden (`"error": "FORBIDDEN"`).
- User A inspecting User A transactions $\rightarrow$ 200 OK.
- User A inspecting User B transactions $\rightarrow$ 403 Forbidden.
- Unauthenticated requests to balance and transactions $\rightarrow$ 401 Unauthorized.
- User A attempting to transfer from User B sender account (with Sanctum token) $\rightarrow$ 403 Forbidden.
- User A transferring from User A sender account (with Sanctum token) $\rightarrow$ 201 Created.
- M2M transfer without user token (HMAC-only) $\rightarrow$ 201 Created.

---

## 9. Database Migrations Added / Modified

1. **`database/migrations/2026_09_22_000001_add_payload_hash_to_transactions_table.php` [NEW]**:
   - Added `payload_hash` column (`VARCHAR(64)`, nullable) to `transactions`.
   - Included safe backfill logic.
2. **`database/migrations/2026_03_11_042143_create_personal_access_tokens_table.php` [MODIFIED]**:
   - Replaced `$table->morphs('tokenable')` with `$table->uuidMorphs('tokenable')` to support UUID users in Sanctum.

---

## 10. Files Changed

### Production Code
- `app/Models/Transaction.php`: Added `payload_hash` to `$fillable`.
- `app/Exceptions/IdempotencyConflictException.php`: Created domain exception for payload mismatch.
- `app/Services/TransferService.php`:
  - Added `calculatePayloadFingerprint(...)` method.
  - Added payload hash validation on Cache hit, DB hit, and TOCTOU race resolution.
  - Included `payload_hash` in `Transaction::create`.
- `app/Http/Controllers/Api/TransactionController.php`:
  - Enforced sender ownership check when authenticated user is present.
  - Added catch block for `IdempotencyConflictException` returning HTTP 409.
- `app/Console/Commands/TestTransferCommand.php`:
  - Handled `IdempotencyConflictException` with structured JSON output.
- `database/migrations/2026_09_22_000001_add_payload_hash_to_transactions_table.php`: Created migration.
- `database/migrations/2026_03_11_042143_create_personal_access_tokens_table.php`: Fixed tokenable UUID type.

### Test Code
- `tests/Feature/TransferTest.php`:
  - Added 5 idempotency mismatch tests.
  - Added canonicalization regression test for equivalent amounts (`100` vs `100.00`).
  - Added mid-transaction failure injection and rollback test.
  - Added HTTP 409 API conflict test.
- `tests/Concurrent/ConcurrencyTest.php`:
  - Added Test 3: 10 parallel processes racing with identical idempotency key and payload.
- `tests/Feature/PostgresConstraintsTest.php` [NEW]:
  - Direct SQL tests for 4 CHECK constraints and 2 append-only triggers.
- `tests/Feature/TransactionStateTest.php` [NEW]:
  - Tests for terminal completed state and atomic rollback on failure.
- `tests/Feature/AuthTest.php` [NEW]:
  - 7 feature tests for Sanctum authentication and token revocation.
- `tests/Feature/AccountAuthorizationTest.php` [NEW]:
  - 8 feature tests for account ownership boundaries and transfer authorization.

---

## 11. Verification Commands & Actual Results

### Configuration Clear
```powershell
php artisan config:clear
```
Output: `Configuration cache cleared successfully.`

### Git Validation
```powershell
git diff --check
```
Output: Code 0 (clean, no whitespace errors).

### Complete Test Suite
```powershell
php artisan test
```
Actual Output:
```text
  Tests:    49 passed (178 assertions)
  Duration: 7.98s
```

Breakdown:
- **Baseline tests retained**: 16 tests (59 assertions) $\rightarrow$ 100% passing.
- **New tests added**: 33 tests (119 assertions) $\rightarrow$ 100% passing.
- **Total**: 49 tests (178 assertions), 0 failures, 0 skipped.

---

## 12. Known Limitations

1. **M2M Authorization Boundary**:
   In Phase 1, `POST /api/v1/transfers` remains supported as an HMAC-signed machine-to-machine endpoint without requiring a Sanctum user token. If a Sanctum user token is provided, sender account ownership is strictly enforced. However, pure HMAC requests do not yet verify which specific client identity owns the `sender_account_id`.
2. **In-Process Cache Layer**:
   Testing relies on PostgreSQL as the source of truth with in-memory array caching (`CACHE_STORE=array`). Redis cache invalidation and distributed lock leases remain deferred.
3. **Queue Execution in Tests**:
   `QUEUE_CONNECTION=sync` is used for test determinism; asynchronous message broker processing is deferred.

---

## 13. Phase 2 Candidates (Concurrency & Reliability)

- Distributed lock coordination / Redis lease-based locking benchmarks.
- Multi-node PostgreSQL connection pooling and transaction retry backoff.
- Chaos testing under simulated PostgreSQL packet drops and latency injection.
- Automated reconciliation worker for transactions stuck during unexpected OS termination.

---

## 14. Phase 3 Candidates (API Security Hardening)

- Mandatory client identification on M2M payment routes (API Key / Client Credentials binding `sender_account_id` to authorized clients).
- Dual-layer authorization: Bearer Token (User) + HMAC / mTLS (Client).
- Cryptographic nonces and timestamp freshness checks on `X-Signature` to prevent replay attacks.
- Sensitive data masking and audit event logging.
