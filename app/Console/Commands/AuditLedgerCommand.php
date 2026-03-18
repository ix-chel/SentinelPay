<?php

namespace App\Console\Commands;

use App\Models\Account;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Audit command to verify ledger integrity against account balances.
 *
 * The ledger is the immutable source of truth. If a discrepancy is found,
 * it means the account.balance column has drifted from the ledger sum.
 *
 * Usage:
 *   php artisan audit:ledger                        — read-only audit
 *   php artisan audit:ledger --account=<uuid>       — audit one account
 *   php artisan audit:ledger --fix                  — audit + reconcile drifted balances
 *   php artisan audit:ledger --fix --force          — same, skip confirmation prompt
 */
class AuditLedgerCommand extends Command
{
    protected $signature = 'audit:ledger
                            {--account= : Audit a specific account by UUID}
                            {--fix      : Reconcile drifted account balances to match the ledger (ledger is source of truth)}
                            {--force    : Skip the confirmation prompt when --fix is used}';

    protected $description = 'Verify that ledger entry sums match each account\'s current balance (financial integrity check)';

    public function handle(): int
    {
        $this->info("╔══════════════════════════════════════════════╗");
        $this->info("║          SentinelPay Ledger Audit            ║");
        $this->info("╚══════════════════════════════════════════════╝");
        $this->newLine();

        $query = Account::query();

        if ($accountId = $this->option("account")) {
            $query->where("id", $accountId);
        }

        $accounts = $query->get();

        if ($accounts->isEmpty()) {
            $this->warn("No accounts found to audit.");
            return self::FAILURE;
        }

        $this->info(sprintf("Auditing %d account(s)...", $accounts->count()));
        $this->newLine();

        $passed = 0;
        $failed = 0;
        $discrepancies = [];

        $headers = [
            "Account ID",
            "Account Balance",
            "Net Ledger Sum",
            "Debit Sum",
            "Credit Sum",
            "Status",
        ];
        $rows = [];

        foreach ($accounts as $account) {
            // Sum all credits and debits independently for this account
            $ledgerSums = DB::table("ledgers")
                ->where("account_id", $account->id)
                ->selectRaw(
                    "
                    SUM(CASE WHEN type = 'credit' THEN amount ELSE 0 END) as total_credits,
                    SUM(CASE WHEN type = 'debit'  THEN amount ELSE 0 END) as total_debits
                ",
                )
                ->first();

            $totalCredits = bcadd(
                (string) ($ledgerSums->total_credits ?? "0"),
                "0",
                2,
            );
            $totalDebits = bcadd(
                (string) ($ledgerSums->total_debits ?? "0"),
                "0",
                2,
            );
            $netLedger = bcsub($totalCredits, $totalDebits, 2);
            $accountBal = bcadd((string) $account->balance, "0", 2);

            $isBalanced = bccomp($netLedger, $accountBal, 2) === 0;

            if ($isBalanced) {
                $passed++;
                $statusLabel = "<fg=green>✓ PASS</>";
            } else {
                $failed++;
                $statusLabel = "<fg=red>✗ FAIL</>";
                $discrepancies[] = [
                    "account_id" => $account->id,
                    "account_balance" => $accountBal,
                    "ledger_net" => $netLedger,
                    "difference" => bcsub($accountBal, $netLedger, 2),
                ];
            }

            $rows[] = [
                substr($account->id, 0, 8) . "...",
                $accountBal,
                $netLedger,
                $totalDebits,
                $totalCredits,
                $statusLabel,
            ];
        }

        $this->table($headers, $rows);
        $this->newLine();

        // Summary
        $this->line(sprintf("<fg=green>Passed: %d</>", $passed));
        $this->line(sprintf("<fg=red>Failed: %d</>", $failed));
        $this->newLine();

        if (empty($discrepancies)) {
            $this->info(
                "✅ All accounts passed financial integrity check. Ledger is consistent.",
            );
            return self::SUCCESS;
        }

        // ── Discrepancies detected ────────────────────────────────────────────
        $this->error("═══ DISCREPANCIES DETECTED ═══");
        $this->newLine();

        foreach ($discrepancies as $d) {
            $sign = bccomp($d["difference"], "0", 2) >= 0 ? "+" : "";
            $this->error(
                sprintf(
                    "Account %s │ account_balance=%s │ ledger_net=%s │ drift=%s%s",
                    $d["account_id"],
                    $d["account_balance"],
                    $d["ledger_net"],
                    $sign,
                    $d["difference"],
                ),
            );
        }

        $this->newLine();

        // ── --fix path ────────────────────────────────────────────────────────
        if (!$this->option("fix")) {
            $this->warn(
                "⚠️  Financial integrity check FAILED. Re-run with --fix to reconcile balances.",
            );
            return self::FAILURE;
        }

        $this->warn(
            "The ledger is the source of truth. The following account balances will be",
        );
        $this->warn("overwritten to match their net ledger sum:");
        $this->newLine();

        foreach ($discrepancies as $d) {
            $this->line(
                sprintf(
                    "  • <fg=yellow>%s</>  %s  →  <fg=cyan>%s</>",
                    $d["account_id"],
                    $d["account_balance"],
                    $d["ledger_net"],
                ),
            );
        }

        $this->newLine();

        // Require explicit confirmation unless --force was supplied
        if (
            !$this->option("force") &&
            !$this->confirm(
                "Are you sure you want to reconcile these account balances?",
                false,
            )
        ) {
            $this->info("Reconciliation cancelled. No changes were made.");
            return self::FAILURE;
        }

        // ── Apply corrections ─────────────────────────────────────────────────
        $corrected = 0;
        $errors = 0;

        foreach ($discrepancies as $d) {
            try {
                DB::transaction(function () use ($d) {
                    // Lock the account row exclusively while we correct it so no
                    // concurrent transfer can observe a partially-updated balance.
                    $account = Account::where("id", $d["account_id"])
                        ->lockForUpdate()
                        ->firstOrFail();

                    // Re-compute the ledger net inside the lock to guard against a
                    // transfer that completed between the audit query above and now.
                    $ledgerSums = DB::table("ledgers")
                        ->where("account_id", $account->id)
                        ->selectRaw(
                            "
                            SUM(CASE WHEN type = 'credit' THEN amount ELSE 0 END) as total_credits,
                            SUM(CASE WHEN type = 'debit'  THEN amount ELSE 0 END) as total_debits
                        ",
                        )
                        ->first();

                    $totalCredits = bcadd(
                        (string) ($ledgerSums->total_credits ?? "0"),
                        "0",
                        2,
                    );
                    $totalDebits = bcadd(
                        (string) ($ledgerSums->total_debits ?? "0"),
                        "0",
                        2,
                    );
                    $freshLedgerNet = bcsub($totalCredits, $totalDebits, 2);

                    // If the account self-corrected (e.g. a concurrent transfer
                    // just committed), skip it rather than making a spurious update.
                    if (
                        bccomp(
                            $freshLedgerNet,
                            bcadd((string) $account->balance, "0", 2),
                            2,
                        ) === 0
                    ) {
                        return;
                    }

                    $account->balance = $freshLedgerNet;
                    $account->save();
                });

                $this->info(
                    sprintf(
                        "  ✓ Fixed account %s  (balance set to %s)",
                        $d["account_id"],
                        $d["ledger_net"],
                    ),
                );

                $corrected++;
            } catch (\Throwable $e) {
                $this->error(
                    sprintf(
                        "  ✗ Failed to fix account %s: %s",
                        $d["account_id"],
                        $e->getMessage(),
                    ),
                );

                $errors++;
            }
        }

        $this->newLine();
        $this->line(sprintf("<fg=green>Corrected: %d</>", $corrected));
        $this->line(sprintf("<fg=red>Errors:    %d</>", $errors));
        $this->newLine();

        if ($errors > 0) {
            $this->warn(
                "⚠️  Some accounts could not be reconciled. Investigate the errors above.",
            );
            return self::FAILURE;
        }

        $this->info(
            "✅ All discrepancies reconciled. Ledger and account balances are now consistent.",
        );
        return self::SUCCESS;
    }
}
