<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\LedgerEntry;
use Illuminate\Console\Command;

class AuditLedgerCommand extends Command
{
    protected $signature = 'audit:ledger
                            {--account= : Audit a specific account by ID}
                            {--fix      : Reconcile drifted account balances to match the latest ledger balance}
                            {--force    : Skip the confirmation prompt when --fix is used}';

    protected $description = 'Verify that account balances match the latest append-only ledger balance';

    public function handle(): int
    {
        $this->info('==============================================');
        $this->info('           SentinelPay Ledger Audit           ');
        $this->info('==============================================');
        $this->newLine();

        $query = Account::query();

        if ($accountId = $this->option('account')) {
            $query->whereKey($accountId);
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
        $skipped = 0;
        $discrepancies = [];

        $headers = [
            'Account ID',
            'Account Balance',
            'Latest Ledger Balance',
            'Debit Sum',
            'Credit Sum',
            'Status',
        ];
        $rows = [];

        foreach ($accounts as $account) {
            $summary = $this->getLedgerSummary((string) $account->id);
            $accountBalance = bcadd((string) ($account->balance ?? 0), '0', 2);

            if ($summary['entry_count'] === 0) {
                $skipped++;

                $rows[] = [
                    substr((string) $account->id, 0, 8).'...',
                    $accountBalance,
                    'N/A',
                    '0.00',
                    '0.00',
                    '<fg=yellow>SKIP</>',
                ];

                continue;
            }

            $latestLedgerBalance = $summary['latest_balance_after'];
            $isBalanced = bccomp($latestLedgerBalance, $accountBalance, 2) === 0;

            if ($isBalanced) {
                $passed++;
                $statusLabel = '<fg=green>PASS</>';
            } else {
                $failed++;
                $statusLabel = '<fg=red>FAIL</>';
                $discrepancies[] = [
                    'account_id' => (string) $account->id,
                    'account_balance' => $accountBalance,
                    'ledger_balance' => $latestLedgerBalance,
                    'difference' => bcsub($accountBalance, $latestLedgerBalance, 2),
                ];
            }

            $rows[] = [
                substr((string) $account->id, 0, 8).'...',
                $accountBalance,
                $latestLedgerBalance,
                $summary['total_debits'],
                $summary['total_credits'],
                $statusLabel,
            ];
        }

        $this->table($headers, $rows);
        $this->newLine();

        $this->line(sprintf('<fg=green>Passed: %d</>', $passed));
        $this->line(sprintf('<fg=red>Failed: %d</>', $failed));
        $this->line(sprintf('<fg=yellow>Skipped: %d</>', $skipped));
        $this->newLine();

        if (empty($discrepancies)) {
            $this->info('Ledger audit completed without drift.');

            return self::SUCCESS;
        }

        $this->error('=== DISCREPANCIES DETECTED ===');
        $this->newLine();

        foreach ($discrepancies as $discrepancy) {
            $sign = bccomp($discrepancy['difference'], '0', 2) >= 0 ? '+' : '';
            $this->error(
                sprintf(
                    'Account %s | account_balance=%s | ledger_balance=%s | drift=%s%s',
                    $discrepancy['account_id'],
                    $discrepancy['account_balance'],
                    $discrepancy['ledger_balance'],
                    $sign,
                    $discrepancy['difference'],
                ),
            );
        }

        $this->newLine();

        if (! $this->option('fix')) {
            $this->warn('Ledger audit failed. Re-run with --fix to reconcile balances.');

            return self::FAILURE;
        }

        $this->warn('The latest ledger balance is the source of truth. The following account balances will be');
        $this->warn('overwritten to match the latest ledger entry balance:');
        $this->newLine();

        foreach ($discrepancies as $discrepancy) {
            $this->line(
                sprintf(
                    '  - <fg=yellow>%s</>  %s  ->  <fg=cyan>%s</>',
                    $discrepancy['account_id'],
                    $discrepancy['account_balance'],
                    $discrepancy['ledger_balance'],
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

        foreach ($discrepancies as $discrepancy) {
            try {
                $account = Account::findOrFail($discrepancy['account_id']);
                $freshSummary = $this->getLedgerSummary((string) $account->id);

                if ($freshSummary['entry_count'] === 0) {
                    continue;
                }

                $freshLedgerBalance = $freshSummary['latest_balance_after'];

                if (bccomp($freshLedgerBalance, bcadd((string) ($account->balance ?? 0), '0', 2), 2) === 0) {
                    continue;
                }

                $account->forceFill(['balance' => $freshLedgerBalance])->save();

                $this->info(sprintf('  Fixed account %s (balance set to %s)', $discrepancy['account_id'], $freshLedgerBalance));
                $corrected++;
            } catch (\Throwable $exception) {
                $this->error(sprintf('  Failed to fix account %s: %s', $discrepancy['account_id'], $exception->getMessage()));
                $errors++;
            }
        }

        $this->newLine();
        $this->line(sprintf('<fg=green>Corrected: %d</>', $corrected));
        $this->line(sprintf('<fg=red>Errors:    %d</>', $errors));
        $this->newLine();

        if ($errors > 0) {
            $this->warn('Some accounts could not be reconciled. Investigate the errors above.');

            return self::FAILURE;
        }

        $this->info('All discrepancies reconciled. Ledger and account balances are now consistent.');

        return self::SUCCESS;
    }

    /**
     * @return array{entry_count: int, latest_balance_after: string|null, total_credits: string, total_debits: string}
     */
    private function getLedgerSummary(string $accountId): array
    {
        $totals = LedgerEntry::query()
            ->where('account_id', $accountId)
            ->selectRaw(
                'COUNT(*) as entry_count, COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE 0 END), 0) as total_credits, COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE 0 END), 0) as total_debits',
                [LedgerEntry::TYPE_CREDIT, LedgerEntry::TYPE_DEBIT],
            )
            ->first();

        $latestBalance = LedgerEntry::query()
            ->where('account_id', $accountId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->value('balance_after');

        return [
            'entry_count' => (int) ($totals?->entry_count ?? 0),
            'latest_balance_after' => $latestBalance === null ? null : bcadd((string) $latestBalance, '0', 2),
            'total_credits' => bcadd((string) ($totals?->total_credits ?? '0'), '0', 2),
            'total_debits' => bcadd((string) ($totals?->total_debits ?? '0'), '0', 2),
        ];
    }
}
