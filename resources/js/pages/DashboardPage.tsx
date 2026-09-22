import React from 'react';
import type { NavigationTab } from '../components/Sidebar';
import { MetricCard } from '../components/MetricCard';
import { TransactionLedger } from '../components/TransactionLedger';
import { SecurityHUD } from '../components/SecurityHUD';
import { StatusIndicator } from '../components/StatusIndicator';
import { GlassCard } from '../components/GlassCard';
import {
    ShieldIcon,
    LockIcon,
    ArrowUpRightIcon,
    ActivityIcon,
    SendIcon,
    KeyIcon,
} from '../components/Icons';
import type { HealthResponse, Transaction } from '../types';

interface DashboardPageProps {
    currentTab: NavigationTab;
    onOpenTransfer: () => void;
    onSelectTab: (tab: NavigationTab) => void;
    health: HealthResponse | null;
    isHealthy: boolean;
    transactions: Transaction[];
    bufferedTransactions: Transaction[];
    onApplyBufferedTransactions: () => void;
    balance: string;
}

export const DashboardPage: React.FC<DashboardPageProps> = ({
    currentTab,
    onOpenTransfer,
    onSelectTab: _onSelectTab,
    health,
    isHealthy,
    transactions,
    bufferedTransactions,
    onApplyBufferedTransactions,
    balance,
}) => {
    // -------------------------------------------------------------
    // RENDER: VIEW 1 - OVERVIEW DASHBOARD
    // -------------------------------------------------------------
    if (currentTab === 'overview') {
        return (
            <div className="space-y-6">
                {/* Institutional KPI Metrics Bar */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <MetricCard
                        title="Primary Vault Liquidity"
                        value={`$${balance} USD`}
                        subtitle="Available for settlement"
                        trend={{ label: '+8.4% 24h', direction: 'up' }}
                        icon={ShieldIcon}
                        accentColor="blue"
                        badgeText="Live Vault"
                    />
                    <MetricCard
                        title="24h Settled Volume"
                        value="$48,250.00 USD"
                        subtitle="100% finality rate"
                        trend={{ label: '34 transfers', direction: 'neutral' }}
                        icon={ArrowUpRightIcon}
                        accentColor="emerald"
                        badgeText="Append-Only"
                    />
                    <MetricCard
                        title="ACID Lock Integrity"
                        value="100% Locked"
                        subtitle="Sorted UUIDs · Zero deadlocks"
                        icon={LockIcon}
                        accentColor="purple"
                        badgeText="Pessimistic"
                    />
                    <MetricCard
                        title="API Gateway Status"
                        value={isHealthy ? '12ms Latency' : 'Degraded'}
                        subtitle={`${health?.service || 'SentinelPay'} · HTTP 200`}
                        icon={ActivityIcon}
                        accentColor={isHealthy ? 'emerald' : 'amber'}
                        badgeText="HMAC-SHA256"
                    />
                </div>

                {/* Operations Hybrid Grid: Ledger (65%) + Security HUD (35%) */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
                    {/* Authoritative Ledger (Left 2 cols on Desktop) */}
                    <div className="lg:col-span-2 space-y-4">
                        <TransactionLedger
                            transactions={transactions}
                            bufferedTransactions={bufferedTransactions}
                            onApplyBufferedTransactions={onApplyBufferedTransactions}
                        />
                    </div>

                    {/* Security & Integrity HUD (Right 1 col on Desktop) */}
                    <div className="space-y-4">
                        <SecurityHUD
                            hmacStatus="verified"
                            lockStatus="enforced"
                            idempotencyStatus="active"
                        />

                        {/* Quick Transfer CTA Card */}
                        <GlassCard variant="interactive" onClick={onOpenTransfer} className="p-5 space-y-3">
                            <div className="flex items-center space-x-3">
                                <div className="p-2.5 rounded-xl bg-blue-500/10 border border-blue-500/20 text-blue-400">
                                    <SendIcon className="w-5 h-5" />
                                </div>
                                <div>
                                    <h4 className="text-sm font-bold text-slate-100">
                                        Execute Protected Transfer
                                    </h4>
                                    <p className="text-xs text-slate-400">
                                        Instant ACID settlement with unique idempotency protection.
                                    </p>
                                </div>
                            </div>
                            <div className="pt-2 flex items-center justify-between text-xs text-blue-400 font-semibold">
                                <span>Initiate Transfer Window →</span>
                                <kbd className="px-1.5 py-0.5 text-[10px] font-mono bg-white/[0.08] text-slate-300 rounded border border-white/[0.1]">
                                    T
                                </kbd>
                            </div>
                        </GlassCard>
                    </div>
                </div>
            </div>
        );
    }

    // -------------------------------------------------------------
    // RENDER: VIEW 2 - FULL AUTHORITATIVE LEDGER
    // -------------------------------------------------------------
    if (currentTab === 'ledger') {
        return (
            <div className="space-y-6">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-2 border-b border-white/[0.06]">
                    <div>
                        <h2 className="text-xl font-bold text-slate-100">
                            Immutable Transaction Ledger
                        </h2>
                        <p className="text-xs text-slate-400">
                            Complete historical audit trail enforced by PostgreSQL row locks and double-entry bookkeeping.
                        </p>
                    </div>
                    <button
                        onClick={onOpenTransfer}
                        className="inline-flex items-center space-x-2 px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold shadow-lg shadow-blue-600/20 transition-all"
                    >
                        <SendIcon className="w-3.5 h-3.5" />
                        <span>New Transfer</span>
                    </button>
                </div>

                <TransactionLedger
                    transactions={transactions}
                    bufferedTransactions={bufferedTransactions}
                    onApplyBufferedTransactions={onApplyBufferedTransactions}
                />
            </div>
        );
    }

    // -------------------------------------------------------------
    // RENDER: VIEW 3 - TRANSFER EXECUTION ENGINE
    // -------------------------------------------------------------
    if (currentTab === 'transfers') {
        return (
            <div className="max-w-4xl mx-auto space-y-6">
                <div className="pb-2 border-b border-white/[0.06]">
                    <h2 className="text-xl font-bold text-slate-100">
                        Transfer Execution Engine
                    </h2>
                    <p className="text-xs text-slate-400">
                        Pessimistic concurrency control and timing-safe signature verification.
                    </p>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {/* Launch Window Card */}
                    <GlassCard variant="default" className="p-6 space-y-4">
                        <div className="flex items-center space-x-3">
                            <div className="p-3 rounded-xl bg-blue-500/10 border border-blue-500/20 text-blue-400">
                                <SendIcon className="w-6 h-6" />
                            </div>
                            <div>
                                <h3 className="text-base font-bold text-slate-100">
                                    Initiate Fund Movement
                                </h3>
                                <p className="text-xs text-slate-400">
                                    Debit sender and credit counterparty atomically.
                                </p>
                            </div>
                        </div>

                        <div className="p-4 rounded-xl bg-slate-900/80 border border-white/[0.06] space-y-2 text-xs">
                            <div className="flex justify-between text-slate-400">
                                <span>Default Sender Vault:</span>
                                <span className="font-mono text-slate-300">a100...0001</span>
                            </div>
                            <div className="flex justify-between text-slate-400">
                                <span>Available Liquidity:</span>
                                <span className="font-mono font-semibold text-slate-100 tabular-nums">
                                    ${balance} USD
                                </span>
                            </div>
                        </div>

                        <button
                            onClick={onOpenTransfer}
                            className="w-full py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold shadow-lg shadow-blue-600/25 transition-all"
                        >
                            Open Transfer Interface →
                        </button>
                    </GlassCard>

                    {/* Security Guarantee Card */}
                    <GlassCard variant="default" className="p-6 space-y-4">
                        <div className="flex items-center space-x-3">
                            <div className="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400">
                                <ShieldIcon className="w-6 h-6" />
                            </div>
                            <div>
                                <h3 className="text-base font-bold text-slate-100">
                                    Guaranteed Idempotency
                                </h3>
                                <p className="text-xs text-slate-400">
                                    Protected against duplicate requests & network replays.
                                </p>
                            </div>
                        </div>

                        <p className="text-xs text-slate-300 leading-relaxed">
                            Each transfer requires a client-generated UUID idempotency key. In case of network retry or duplicate submission, SentinelPay automatically identifies the existing transaction and prevents double-debiting.
                        </p>

                        <div className="pt-2 text-xs text-slate-400 flex items-center space-x-2">
                            <span className="w-2 h-2 rounded-full bg-emerald-400" />
                            <span>PostgreSQL Unique Constraints Active</span>
                        </div>
                    </GlassCard>
                </div>
            </div>
        );
    }

    // -------------------------------------------------------------
    // RENDER: VIEW 4 - SECURITY & INTEGRITY HUD
    // -------------------------------------------------------------
    if (currentTab === 'security') {
        return (
            <div className="max-w-4xl mx-auto space-y-6">
                <div className="pb-2 border-b border-white/[0.06]">
                    <h2 className="text-xl font-bold text-slate-100">
                        Security & Risk Control Center
                    </h2>
                    <p className="text-xs text-slate-400">
                        Real-time audit signals, cryptographic verification status, and concurrency monitors.
                    </p>
                </div>

                <SecurityHUD />

                {/* Technical Architecture Specs */}
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                    <div className="p-4 rounded-xl bg-[#0F172A] border border-white/[0.08] space-y-1.5">
                        <div className="font-semibold text-slate-200 flex items-center space-x-2">
                            <KeyIcon className="w-4 h-4 text-emerald-400" />
                            <span>HMAC-SHA256 Mutating Boundary</span>
                        </div>
                        <p className="text-slate-400 leading-relaxed">
                            Payload hashes compared using timing-safe hash_equals to block side-channel attacks.
                        </p>
                    </div>

                    <div className="p-4 rounded-xl bg-[#0F172A] border border-white/[0.08] space-y-1.5">
                        <div className="font-semibold text-slate-200 flex items-center space-x-2">
                            <LockIcon className="w-4 h-4 text-sky-400" />
                            <span>Row-Level Concurrency Lock</span>
                        </div>
                        <p className="text-slate-400 leading-relaxed">
                            Sorted account UUIDs guarantee deterministic lock acquisition order, eliminating deadlocks.
                        </p>
                    </div>
                </div>
            </div>
        );
    }

    // -------------------------------------------------------------
    // RENDER: VIEW 5 - ARCHITECTURE TOPOLOGY
    // -------------------------------------------------------------
    return (
        <div className="space-y-6">
            <div className="pb-2 border-b border-white/[0.06]">
                <h2 className="text-xl font-bold text-slate-100">
                    System Architecture & Topology
                </h2>
                <p className="text-xs text-slate-400">
                    Local native service status and environment connectivity.
                </p>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <StatusIndicator
                    label="React 19 + Vite Frontend"
                    status="healthy"
                    detail="TypeScript · Tailwind CSS · Axios API Client"
                />
                <StatusIndicator
                    label="Laravel 12 API Gateway"
                    status={isHealthy ? 'healthy' : 'error'}
                    detail={isHealthy ? `Online (${health?.service})` : 'Offline / Degraded'}
                />
                <StatusIndicator
                    label="Supabase PostgreSQL"
                    status="connected"
                    detail="Authoritative DB · Row Locks · Append-Only Ledger"
                />
                <StatusIndicator
                    label="Queue Processing"
                    status="healthy"
                    detail="QUEUE_CONNECTION=sync (Synchronous Local)"
                />
                <StatusIndicator
                    label="Cache & Sessions"
                    status="healthy"
                    detail="CACHE_STORE=file · SESSION_DRIVER=file"
                />
                <StatusIndicator
                    label="Redis & RabbitMQ"
                    status="deferred"
                    detail="Deferred for Phase 4 / Phase 5 Deployment"
                />
            </div>
        </div>
    );
};
