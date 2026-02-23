<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Ledger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Audit command to verify ledger integrity against account balances.
 *
 * Usage: php artisan audit:ledger
 * Usage: php artisan audit:ledger --account=<uuid>
 */
class AuditLedgerCommand extends Command
{
    protected $signature = 'audit:ledger
                            {--account= : Audit a specific account by UUID}
                            {--fix      : Output discrepancies but do not fix them automatically}';

    protected $description = 'Verify that ledger entry sums match each account\'s current balance (financial integrity check)';

    public function handle(): int
    {
        $this->info('╔══════════════════════════════════════════════╗');
        $this->info('║          SentinelPay Ledger Audit            ║');
        $this->info('╚══════════════════════════════════════════════╝');
        $this->newLine();

        $query = Account::query();

        if ($accountId = $this->option('account')) {
            $query->where('id', $accountId);
        }

        $accounts = $query->get();

        if ($accounts->isEmpty()) {
            $this->warn('No accounts found to audit.');
            return self::FAILURE;
        }

        $this->info(sprintf('Auditing %d account(s)...', $accounts->count()));
        $this->newLine();

        $passed       = 0;
        $failed       = 0;
        $discrepancies = [];

        $headers = ['Account ID', 'Account Balance', 'Net Ledger Sum', 'Debit Sum', 'Credit Sum', 'Status'];
        $rows    = [];

        foreach ($accounts as $account) {
            // Sum all credits and debits independently for this account
            $ledgerSums = DB::table('ledgers')
                ->where('account_id', $account->id)
                ->selectRaw("
                    SUM(CASE WHEN type = 'credit' THEN amount ELSE 0 END) as total_credits,
                    SUM(CASE WHEN type = 'debit'  THEN amount ELSE 0 END) as total_debits
                ")
                ->first();

            $totalCredits = bcadd((string) ($ledgerSums->total_credits ?? '0'), '0', 2);
            $totalDebits  = bcadd((string) ($ledgerSums->total_debits  ?? '0'), '0', 2);
            $netLedger    = bcsub($totalCredits, $totalDebits, 2);
            $accountBal   = bcadd((string) $account->balance, '0', 2);

            $isBalanced = bccomp($netLedger, $accountBal, 2) === 0;

            if ($isBalanced) {
                $passed++;
                $statusLabel = '<fg=green>✓ PASS</>';
            } else {
                $failed++;
                $statusLabel = '<fg=red>✗ FAIL</>';
                $discrepancies[] = [
                    'account_id'      => $account->id,
                    'account_balance' => $accountBal,
                    'ledger_net'      => $netLedger,
                    'difference'      => bcsub($accountBal, $netLedger, 2),
                ];
            }

            $rows[] = [
                substr($account->id, 0, 8) . '...',
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
        $this->line(sprintf('<fg=green>Passed: %d</>', $passed));
        $this->line(sprintf('<fg=red>Failed: %d</>', $failed));
        $this->newLine();

        if (! empty($discrepancies)) {
            $this->error('═══ DISCREPANCIES DETECTED ═══');
            $this->newLine();

            foreach ($discrepancies as $d) {
                $this->error(sprintf(
                    'Account %s: Balance=%s, Ledger Net=%s, Difference=%s',
                    $d['account_id'],
                    $d['account_balance'],
                    $d['ledger_net'],
                    $d['difference']
                ));
            }

            $this->newLine();
            $this->warn('⚠️  Financial integrity check FAILED. Investigate immediately.');

            return self::FAILURE;
        }

        $this->info('✅ All accounts passed financial integrity check. Ledger is consistent.');

        return self::SUCCESS;
    }
}
