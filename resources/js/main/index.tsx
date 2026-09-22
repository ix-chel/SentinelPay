import React, { useState, useEffect, useCallback } from 'react';
import { AppLayout } from '../layouts/AppLayout';
import { DashboardPage } from '../pages/DashboardPage';
import { TransferModal } from '../components/TransferModal';
import { CommandPalette } from '../components/CommandPalette';
import type { NavigationTab } from '../components/Sidebar';
import type { HealthResponse, Transaction } from '../types';
import { api } from '../lib/api';

export const MainApp: React.FC = () => {
    const [currentTab, setCurrentTab] = useState<NavigationTab>('overview');
    const [isSearchOpen, setIsSearchOpen] = useState(false);
    const [isTransferOpen, setIsTransferOpen] = useState(false);

    // API & State
    const [health, setHealth] = useState<HealthResponse | null>(null);
    const [isHealthy, setIsHealthy] = useState<boolean>(true);
    const [balance, setBalance] = useState<string>('10000.00');

    // Initial Authoritative Transactions (Matching Seeder Accounts)
    const [transactions, setTransactions] = useState<Transaction[]>([
        {
            id: '8f31b20a-42c1-40be-9812-32b001a1e001',
            idempotency_key: 'idem-seed-init-001',
            sender_id: 'a1000000-0000-0000-0000-000000000001',
            receiver_id: 'b2000000-0000-0000-0000-000000000002',
            amount: '150.00',
            currency: 'USD',
            status: 'completed',
            created_at: new Date(Date.now() - 1000 * 60 * 5).toISOString(),
            type: 'debit',
            risk_level: 'low',
        },
        {
            id: '7c40a19e-15b2-48ae-8701-11c990b2f002',
            idempotency_key: 'idem-seed-init-002',
            sender_id: 'b2000000-0000-0000-0000-000000000002',
            receiver_id: 'a1000000-0000-0000-0000-000000000001',
            amount: '500.00',
            currency: 'USD',
            status: 'completed',
            created_at: new Date(Date.now() - 1000 * 60 * 25).toISOString(),
            type: 'credit',
            risk_level: 'low',
        },
        {
            id: '6b29f08d-94a1-43fd-a512-00b881c3e003',
            idempotency_key: 'idem-seed-conflict-sample',
            sender_id: 'a1000000-0000-0000-0000-000000000001',
            receiver_id: 'b2000000-0000-0000-0000-000000000002',
            amount: '220.00',
            currency: 'USD',
            status: 'conflict',
            failure_reason: 'Duplicate request signature matched existing key',
            created_at: new Date(Date.now() - 1000 * 60 * 50).toISOString(),
            type: 'debit',
            risk_level: 'medium',
        },
    ]);

    // Buffered Incoming Queue (Zero-Shift Policy when user is reading)
    const [bufferedTransactions, setBufferedTransactions] = useState<Transaction[]>([]);

    // Fetch initial health from Laravel API
    const checkHealth = useCallback(() => {
        api.getHealth()
            .then((data) => {
                setHealth(data);
                setIsHealthy(data.status === 'ok');
            })
            .catch(() => {
                setIsHealthy(false);
            });
    }, []);

    useEffect(() => {
        checkHealth();
        const interval = setInterval(checkHealth, 30000);
        return () => clearInterval(interval);
    }, [checkHealth]);

    // Global keyboard shortcuts: Cmd+K / Ctrl+K and T
    useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            const isInputActive =
                document.activeElement?.tagName === 'INPUT' ||
                document.activeElement?.tagName === 'TEXTAREA' ||
                document.activeElement?.tagName === 'SELECT';

            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                setIsSearchOpen((prev) => !prev);
            } else if (e.key.toLowerCase() === 't' && !isInputActive && !isSearchOpen && !isTransferOpen) {
                e.preventDefault();
                setIsTransferOpen(true);
            }
        };

        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [isSearchOpen, isTransferOpen]);

    // Handle Transfer Success
    const handleTransferSuccess = (newTx: Transaction) => {
        // Prepend directly to ledger
        setTransactions((prev) => [newTx, ...prev]);

        // Update balance
        const currentNum = parseFloat(balance);
        const debitNum = parseFloat(newTx.amount);
        if (!isNaN(currentNum) && !isNaN(debitNum)) {
            setBalance((currentNum - debitNum).toFixed(2));
        }
    };

    // Apply buffered transactions into the active list
    const handleApplyBufferedTransactions = () => {
        setTransactions((prev) => [...bufferedTransactions, ...prev]);
        setBufferedTransactions([]);
    };

    // Handle Conflict Resolution: Auto-focus or navigate to existing transaction
    const handleResolveConflict = (_existingTx: Partial<Transaction>) => {
        setCurrentTab('ledger');
    };

    return (
        <AppLayout
            currentTab={currentTab}
            onSelectTab={setCurrentTab}
            onOpenSearch={() => setIsSearchOpen(true)}
            onOpenTransfer={() => setIsTransferOpen(true)}
            isHealthy={isHealthy}
            serviceName={health?.service || 'SentinelPay'}
        >
            <DashboardPage
                currentTab={currentTab}
                onOpenTransfer={() => setIsTransferOpen(true)}
                onSelectTab={setCurrentTab}
                health={health}
                isHealthy={isHealthy}
                transactions={transactions}
                bufferedTransactions={bufferedTransactions}
                onApplyBufferedTransactions={handleApplyBufferedTransactions}
                balance={balance}
            />

            {/* Transfer Execution Modal */}
            <TransferModal
                isOpen={isTransferOpen}
                onClose={() => setIsTransferOpen(false)}
                onSuccess={handleTransferSuccess}
                onResolveConflict={handleResolveConflict}
                senderBalance={balance}
            />

            {/* Global Command Palette */}
            <CommandPalette
                isOpen={isSearchOpen}
                onClose={() => setIsSearchOpen(false)}
                onSelectTab={setCurrentTab}
                onOpenTransfer={() => setIsTransferOpen(true)}
            />
        </AppLayout>
    );
};
