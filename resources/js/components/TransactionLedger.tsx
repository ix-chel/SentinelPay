import React, { useState } from 'react';
import type { Transaction, TransactionStatus } from '../types';
import {
    CheckCircleIcon,
    ClockIcon,
    AlertTriangleIcon,
    XCircleIcon,
    CopyIcon,
    CheckIcon,
    SearchIcon,
    ChevronDownIcon,
    ArrowUpRightIcon,
    ArrowDownLeftIcon,
} from './Icons';

interface TransactionLedgerProps {
    transactions: Transaction[];
    bufferedTransactions?: Transaction[];
    onApplyBufferedTransactions?: () => void;
    onSelectTransaction?: (transaction: Transaction) => void;
    isLoading?: boolean;
}

export const TransactionLedger: React.FC<TransactionLedgerProps> = ({
    transactions,
    bufferedTransactions = [],
    onApplyBufferedTransactions,
    onSelectTransaction,
    isLoading = false,
}) => {
    const [searchQuery, setSearchQuery] = useState('');
    const [statusFilter, setStatusFilter] = useState<string>('all');
    const [copiedKey, setCopiedKey] = useState<string | null>(null);

    const handleCopy = (e: React.MouseEvent, text: string) => {
        e.stopPropagation();
        navigator.clipboard.writeText(text);
        setCopiedKey(text);
        setTimeout(() => setCopiedKey(null), 1500);
    };

    const filteredTransactions = transactions.filter((tx) => {
        const matchesStatus =
            statusFilter === 'all' ||
            tx.status === statusFilter ||
            (statusFilter === 'failed' && (tx.status === 'failed' || tx.status === 'conflict'));
        const query = searchQuery.toLowerCase().trim();
        const matchesQuery =
            !query ||
            tx.id.toLowerCase().includes(query) ||
            tx.idempotency_key.toLowerCase().includes(query) ||
            tx.amount.includes(query) ||
            (tx.sender_id && tx.sender_id.toLowerCase().includes(query)) ||
            (tx.receiver_id && tx.receiver_id.toLowerCase().includes(query));
        return matchesStatus && matchesQuery;
    });

    const getStatusBadge = (status: TransactionStatus) => {
        switch (status) {
            case 'completed':
                return (
                    <span className="inline-flex items-center space-x-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                        <CheckCircleIcon className="w-3.5 h-3.5" />
                        <span>Completed</span>
                    </span>
                );
            case 'pending':
            case 'processing':
                return (
                    <span className="inline-flex items-center space-x-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-500/10 text-amber-400 border border-amber-500/20">
                        <ClockIcon className="w-3.5 h-3.5" />
                        <span>Pending</span>
                    </span>
                );
            case 'conflict':
                return (
                    <span className="inline-flex items-center space-x-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-rose-500/10 text-rose-400 border border-rose-500/20">
                        <AlertTriangleIcon className="w-3.5 h-3.5" />
                        <span>Conflict</span>
                    </span>
                );
            case 'failed':
            default:
                return (
                    <span className="inline-flex items-center space-x-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-rose-500/10 text-rose-400 border border-rose-500/20">
                        <XCircleIcon className="w-3.5 h-3.5" />
                        <span>Failed</span>
                    </span>
                );
        }
    };

    return (
        <div className="rounded-2xl border border-white/[0.08] bg-[#0F172A] shadow-xl overflow-hidden flex flex-col">
            {/* Header & Controls Toolbar */}
            <div className="p-4 sm:p-5 border-b border-white/[0.08] flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 bg-[#0F172A]">
                <div>
                    <div className="flex items-center space-x-2">
                        <h2 className="text-base font-bold text-slate-100 tracking-tight">
                            Authoritative Ledger
                        </h2>
                        <span className="px-2 py-0.5 text-[11px] font-mono rounded bg-slate-800 text-slate-400 border border-white/[0.06]">
                            PostgreSQL Append-Only
                        </span>
                    </div>
                    <p className="text-xs text-slate-400 mt-0.5">
                        ACID state history with cryptographic idempotency tracking.
                    </p>
                </div>

                <div className="flex items-center space-x-2">
                    {/* Search Field */}
                    <div className="relative flex-1 sm:w-60">
                        <SearchIcon className="w-3.5 h-3.5 text-slate-500 absolute left-3 top-1/2 -translate-y-1/2" />
                        <input
                            type="text"
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            placeholder="Filter by key / UUID..."
                            className="w-full pl-8 pr-3 py-1.5 rounded-xl bg-slate-900 border border-white/[0.08] text-xs text-slate-200 placeholder-slate-500 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                        />
                    </div>

                    {/* Status Filter */}
                    <div className="relative flex-shrink-0">
                        <select
                            value={statusFilter}
                            onChange={(e) => setStatusFilter(e.target.value)}
                            className="appearance-none pl-3 pr-8 py-1.5 rounded-xl bg-slate-900 border border-white/[0.08] text-xs text-slate-300 focus:outline-none focus:border-blue-500 cursor-pointer"
                        >
                            <option value="all">All States</option>
                            <option value="completed">Completed</option>
                            <option value="pending">Pending</option>
                            <option value="conflict">Conflict</option>
                            <option value="failed">Failed</option>
                        </select>
                        <ChevronDownIcon className="w-3.5 h-3.5 text-slate-400 absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                    </div>
                </div>
            </div>

            {/* Buffered Live Insertion Pill (Zero Shift Policy) */}
            {bufferedTransactions.length > 0 && (
                <div className="relative z-10 flex justify-center py-2 bg-blue-950/40 border-b border-blue-500/20">
                    <button
                        onClick={onApplyBufferedTransactions}
                        className="inline-flex items-center space-x-2 px-3 py-1 rounded-full text-xs font-semibold bg-blue-500/20 hover:bg-blue-500/30 text-blue-300 border border-blue-500/40 shadow-sm transition-all duration-150 animate-bounce"
                    >
                        <span>{bufferedTransactions.length} new transactions in queue</span>
                        <span>·</span>
                        <span className="underline">View new transactions ↓</span>
                    </button>
                </div>
            )}

            {/* Dense Ledger Table on Solid Surface (#0F172A) */}
            <div className="overflow-x-auto">
                <table className="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr className="border-b border-white/[0.06] bg-slate-900/60 text-slate-400 font-medium">
                            <th className="py-3 px-4">Transaction / Idempotency Key</th>
                            <th className="py-3 px-4">Type & Flow</th>
                            <th className="py-3 px-4">Amount</th>
                            <th className="py-3 px-4">Status</th>
                            <th className="py-3 px-4 text-right">Timestamp</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-white/[0.04]">
                        {isLoading ? (
                            <tr>
                                <td colSpan={5} className="py-12 text-center text-slate-500">
                                    <div className="inline-flex items-center space-x-2">
                                        <div className="w-4 h-4 rounded-full border-2 border-blue-500 border-t-transparent animate-spin" />
                                        <span>Reading authoritative ledger...</span>
                                    </div>
                                </td>
                            </tr>
                        ) : filteredTransactions.length === 0 ? (
                            <tr>
                                <td colSpan={5} className="py-12 text-center text-slate-500">
                                    No transaction records found matching your filters.
                                </td>
                            </tr>
                        ) : (
                            filteredTransactions.map((tx) => {
                                const isDebit = tx.type === 'debit' || !tx.type;
                                const isNegative = isDebit && tx.status !== 'failed' && tx.status !== 'conflict';

                                return (
                                    <tr
                                        key={tx.id}
                                        onClick={() => onSelectTransaction?.(tx)}
                                        className="hover:bg-slate-800/50 transition-colors duration-150 cursor-pointer group"
                                    >
                                        {/* ID & Key */}
                                        <td className="py-3 px-4">
                                            <div className="flex items-center space-x-2">
                                                <span className="font-mono text-slate-200 group-hover:text-blue-400 transition-colors">
                                                    TX-{tx.id.substring(0, 8)}
                                                </span>
                                                <button
                                                    onClick={(e) => handleCopy(e, tx.idempotency_key)}
                                                    className="p-1 rounded text-slate-500 hover:text-slate-300 hover:bg-slate-800 transition-colors"
                                                    title={`Copy Key: ${tx.idempotency_key}`}
                                                    aria-label="Copy Idempotency Key"
                                                >
                                                    {copiedKey === tx.idempotency_key ? (
                                                        <CheckIcon className="w-3 h-3 text-emerald-400" />
                                                    ) : (
                                                        <CopyIcon className="w-3 h-3" />
                                                    )}
                                                </button>
                                            </div>
                                            <div className="font-mono text-[10px] text-slate-400 truncate max-w-xs mt-0.5">
                                                key: {tx.idempotency_key}
                                            </div>
                                        </td>

                                        {/* Flow / Direction */}
                                        <td className="py-3 px-4">
                                            <div className="flex items-center space-x-1.5 text-slate-300">
                                                {isDebit ? (
                                                    <ArrowUpRightIcon className="w-3.5 h-3.5 text-slate-400" />
                                                ) : (
                                                    <ArrowDownLeftIcon className="w-3.5 h-3.5 text-emerald-400" />
                                                )}
                                                <span className="capitalize font-medium">
                                                    {isDebit ? 'Transfer Out' : 'Transfer In'}
                                                </span>
                                            </div>
                                            <div className="text-[10px] text-slate-400 font-mono mt-0.5">
                                                acc: {tx.receiver_id ? tx.receiver_id.substring(0, 8) + '...' : 'System'}
                                            </div>
                                        </td>

                                        {/* Financial Amount */}
                                        <td className="py-3 px-4">
                                            <div className="font-semibold tabular-nums text-slate-100">
                                                {isNegative ? `-$${tx.amount}` : `$${tx.amount}`}{' '}
                                                <span className="text-[10px] font-normal text-slate-400">
                                                    {tx.currency}
                                                </span>
                                            </div>
                                            <div className="text-[10px] text-slate-400 mt-0.5">
                                                {isDebit ? 'Debit' : 'Credit'}
                                            </div>
                                        </td>

                                        {/* Status Badge (Dual Encoding) */}
                                        <td className="py-3 px-4">
                                            {getStatusBadge(tx.status)}
                                            {tx.failure_reason && (
                                                <div className="text-[10px] text-rose-400 truncate max-w-xs mt-1">
                                                    {tx.failure_reason}
                                                </div>
                                            )}
                                        </td>

                                        {/* Timestamp */}
                                        <td className="py-3 px-4 text-right">
                                            <div className="font-mono text-slate-300 text-xs tabular-nums">
                                                {new Date(tx.created_at).toLocaleTimeString([], {
                                                    hour: '2-digit',
                                                    minute: '2-digit',
                                                    second: '2-digit',
                                                })}
                                            </div>
                                            <div className="text-[10px] text-slate-400 tabular-nums mt-0.5">
                                                {new Date(tx.created_at).toLocaleDateString([], {
                                                    month: 'short',
                                                    day: 'numeric',
                                                })}
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })
                        )}
                    </tbody>
                </table>
            </div>

            {/* Table Footer Summary */}
            <div className="p-3 border-t border-white/[0.06] bg-slate-900/60 flex items-center justify-between text-xs text-slate-400">
                <span>
                    Showing <strong className="text-slate-200">{filteredTransactions.length}</strong> of{' '}
                    <strong className="text-slate-200">{transactions.length}</strong> recorded ledger rows
                </span>
                <span className="font-mono text-[11px] text-slate-400">
                    Row-Level Locking Enforced
                </span>
            </div>
        </div>
    );
};
