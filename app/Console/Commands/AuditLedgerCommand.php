<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\LedgerEntry;
use Illuminate\Console\Command;

class AuditLedgerCommand extends Command
{
    protected $signature = 'audit:ledger
                            {--account= : Audit a specific account by ID}
                            {--fix      : Reconcile drifted account balances to match the ledger (ledger is source of truth)}
                            {--force    : Skip the confirmation prompt when --fix is used}';

    protected $description = 'Verify that ledger entry sums match each account\'s current balance (financial integrity check)';

    public function handle(): int
    {
        $this->info('╔══════════════════════════════════════════════╗');
        $this->info('║          SentinelPay Ledger Audit            ║');
        $this->info('╚══════════════════════════════════════════════╝');
        $this->newLine();

        $query = Account::query();

        if ($accountId = $this->option('account')) {
            $query->where('_id', $accountId);
        }

        $accounts = $query->get();

        if ($accounts->isEmpty()) {
            $this->warn('No accounts found to audit.');

            return self::FAILURE;
        }

        $this->info(sprintf('Auditing %d account(s)...', $accounts->count()));
        $this->newLine();

        $passed = 0;
        $failed = 0;
        $discrepancies = [];

        $headers = [
            'Account ID',
            'Account Balance',
            'Net Ledger Sum',
            'Debit Sum',
            'Credit Sum',
            'Status',
        ];
        $rows = [];

        foreach ($accounts as $account) {
            $totals = $this->getLedgerSums($account->id);

            $totalCredits = bcadd((string) ($totals['total_credits'] ?? '0'), '0', 2);
            $totalDebits = bcadd((string) ($totals['total_debits'] ?? '0'), '0', 2);
            $netLedger = bcsub($totalCredits, $totalDebits, 2);
            $accountBal = bcadd((string) ($account->balance ?? 0), '0', 2);

            $isBalanced = bccomp($netLedger, $accountBal, 2) === 0;

            if ($isBalanced) {
                $passed++;
                $statusLabel = '<fg=green>✓ PASS</>';
            } else {
                $failed++;
                $statusLabel = '<fg=red>✗ FAIL</>';
                $discrepancies[] = [
                    'account_id' => (string) $account->id,
                    'account_balance' => $accountBal,
                    'ledger_net' => $netLedger,
                    'difference' => bcsub($accountBal, $netLedger, 2),
                ];
            }

            $rows[] = [
                substr((string) $account->id, 0, 8).'...',
                $accountBal,
                $netLedger,
                $totalDebits,
                $totalCredits,
                $statusLabel,
            ];
        }

        $this->table($headers, $rows);
        $this->newLine();

        $this->line(sprintf('<fg=green>Passed: %d</>', $passed));
        $this->line(sprintf('<fg=red>Failed: %d</>', $failed));
        $this->newLine();

        if (empty($discrepancies)) {
            $this->info('✅ All accounts passed financial integrity check. Ledger is consistent.');

            return self::SUCCESS;
        }

        $this->error('═══ DISCREPANCIES DETECTED ═══');
        $this->newLine();

        foreach ($discrepancies as $d) {
            $sign = bccomp($d['difference'], '0', 2) >= 0 ? '+' : '';
            $this->error(
                sprintf(
                    'Account %s │ account_balance=%s │ ledger_net=%s │ drift=%s%s',
                    $d['account_id'],
                    $d['account_balance'],
                    $d['ledger_net'],
                    $sign,
                    $d['difference'],
                ),
            );
        }

        $this->newLine();

        if (! $this->option('fix')) {
            $this->warn('⚠️  Financial integrity check FAILED. Re-run with --fix to reconcile balances.');

            return self::FAILURE;
        }

        $this->warn('The ledger is the source of truth. The following account balances will be');
        $this->warn('overwritten to match their net ledger sum:');
        $this->newLine();

        foreach ($discrepancies as $d) {
            $this->line(
                sprintf(
                    '  • <fg=yellow>%s</>  %s  →  <fg=cyan>%s</>',
                    $d['account_id'],
                    $d['account_balance'],
                    $d['ledger_net'],
                ),
            );
        }

        $this->newLine();

        if (! $this->option('force') && ! $this->confirm('Are you sure you want to reconcile these account balances?', false)) {
            $this->info('Reconciliation cancelled. No changes were made.');

            return self::FAILURE;
        }

        $corrected = 0;
        $errors = 0;

        foreach ($discrepancies as $d) {
            try {
                $account = Account::findOrFail($d['account_id']);

                $freshTotals = $this->getLedgerSums($account->id);
                $totalCredits = bcadd((string) ($freshTotals['total_credits'] ?? '0'), '0', 2);
                $totalDebits = bcadd((string) ($freshTotals['total_debits'] ?? '0'), '0', 2);
                $freshLedgerNet = bcsub($totalCredits, $totalDebits, 2);

                if (bccomp($freshLedgerNet, bcadd((string) ($account->balance ?? 0), '0', 2), 2) === 0) {
                    continue;
                }

                $account->balance = (float) $freshLedgerNet;
                $account->save();

                $this->info(
                    sprintf(
                        '  ✓ Fixed account %s  (balance set to %s)',
                        $d['account_id'],
                        $d['ledger_net'],
                    ),
                );

                $corrected++;
            } catch (\Throwable $e) {
                $this->error(
                    sprintf(
                        '  ✗ Failed to fix account %s: %s',
                        $d['account_id'],
                        $e->getMessage(),
                    ),
                );

                $errors++;
            }
        }

        $this->newLine();
        $this->line(sprintf('<fg=green>Corrected: %d</>', $corrected));
        $this->line(sprintf('<fg=red>Errors:    %d</>', $errors));
        $this->newLine();

        if ($errors > 0) {
            $this->warn('⚠️  Some accounts could not be reconciled. Investigate the errors above.');

            return self::FAILURE;
        }

        $this->info('✅ All discrepancies reconciled. Ledger and account balances are now consistent.');

        return self::SUCCESS;
    }

    private function getLedgerSums($accountId)
    {
        $pipeline = [
            ['$match' => ['account_id' => $accountId]],
            ['$group' => [
                '_id' => null,
                'total_credits' => ['$sum' => ['$cond' => [['$eq' => ['$type', 'credit']], '$amount', 0]]],
                'total_debits' => ['$sum' => ['$cond' => [['$eq' => ['$type', 'debit']], '$amount', 0]]],
            ]],
        ];

        $results = LedgerEntry::raw(function ($collection) use ($pipeline) {
            return $collection->aggregate($pipeline);
        });

        $resultsArray = is_array($results) ? $results : iterator_to_array($results);
        $totals = ! empty($resultsArray) ? (array) $resultsArray[0] : null;

        return [
            'total_credits' => $totals['total_credits'] ?? 0,
            'total_debits' => $totals['total_debits'] ?? 0,
        ];
    }
}
