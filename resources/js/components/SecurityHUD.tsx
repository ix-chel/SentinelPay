import React from 'react';
import { GlassCard } from './GlassCard';
import type { SecurityAlert } from '../types';
import {
    ShieldIcon,
    LockIcon,
    AlertTriangleIcon,
    CheckCircleIcon,
    ClockIcon,
    KeyIcon,
    TerminalIcon,
} from './Icons';

interface SecurityHUDProps {
    alerts?: SecurityAlert[];
    hmacStatus?: 'verified' | 'degraded';
    lockStatus?: 'enforced' | 'releasing';
    idempotencyStatus?: 'active' | 'degraded';
}

export const SecurityHUD: React.FC<SecurityHUDProps> = ({
    alerts = [],
    hmacStatus = 'verified',
    lockStatus = 'enforced',
    idempotencyStatus = 'active',
}) => {
    const defaultAlerts: SecurityAlert[] = alerts.length > 0 ? alerts : [
        {
            id: 'alt-1',
            severity: 'info',
            title: 'Replay Protection Active',
            message: 'Idempotency filter intercepted 2 duplicate requests with cached responses.',
            timestamp: '2m ago',
            code: 'IDEMPOTENCY_FILTER_OK',
        },
        {
            id: 'alt-2',
            severity: 'warning',
            title: 'Velocity Limit Headroom',
            message: 'Transfer endpoint utilization at 12/30 req/min per IP throttle limit.',
            timestamp: '8m ago',
            code: 'THROTTLE_HEADROOM_40',
        },
    ];

    const getSeverityBadge = (severity: SecurityAlert['severity']) => {
        switch (severity) {
            case 'critical':
                return {
                    badge: 'bg-rose-500/10 text-rose-400 border-rose-500/30',
                    icon: AlertTriangleIcon,
                    prefix: '!',
                };
            case 'warning':
                return {
                    badge: 'bg-amber-500/10 text-amber-400 border-amber-500/30',
                    icon: AlertTriangleIcon,
                    prefix: '△',
                };
            case 'info':
            default:
                return {
                    badge: 'bg-blue-500/10 text-blue-400 border-blue-500/30',
                    icon: ClockIcon,
                    prefix: 'ⓘ',
                };
        }
    };

    return (
        <GlassCard variant="default" className="p-5 flex flex-col space-y-5">
            {/* Header */}
            <div className="flex items-center justify-between pb-3 border-b border-white/[0.08]">
                <div className="flex items-center space-x-2.5">
                    <div className="p-2 rounded-xl bg-blue-500/10 border border-blue-500/20 text-blue-400">
                        <ShieldIcon className="w-4 h-4" />
                    </div>
                    <div>
                        <h3 className="text-sm font-bold text-slate-100 tracking-tight">
                            Security & Integrity HUD
                        </h3>
                        <p className="text-[11px] text-slate-400">
                            Cryptographic verification and concurrency monitors.
                        </p>
                    </div>
                </div>
                <span className="px-2 py-0.5 rounded-full text-[10px] font-mono font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                    SOC-Grade Active
                </span>
            </div>

            {/* Core Integrity Pillars */}
            <div className="grid grid-cols-1 gap-2.5">
                {/* HMAC Verification Pillar */}
                <div className="p-3 rounded-xl bg-slate-900/80 border border-white/[0.06] flex items-center justify-between">
                    <div className="flex items-center space-x-2.5">
                        <KeyIcon className="w-4 h-4 text-emerald-400 flex-shrink-0" />
                        <div>
                            <div className="text-xs font-semibold text-slate-200">
                                HMAC-SHA256 Signature
                            </div>
                            <div className="text-[10px] text-slate-400 font-mono">
                                Timing-safe verification on mutating transfers
                            </div>
                        </div>
                    </div>
                    <span className={`inline-flex items-center space-x-1 text-[11px] font-medium px-2 py-0.5 rounded border ${
                        hmacStatus === 'verified'
                            ? 'text-emerald-400 bg-emerald-500/10 border-emerald-500/20'
                            : 'text-amber-400 bg-amber-500/10 border-amber-500/20'
                    }`}>
                        <CheckCircleIcon className="w-3 h-3" />
                        <span>{hmacStatus === 'verified' ? 'Verified' : 'Degraded'}</span>
                    </span>
                </div>

                {/* Pessimistic Locking Pillar */}
                <div className="p-3 rounded-xl bg-slate-900/80 border border-white/[0.06] flex items-center justify-between">
                    <div className="flex items-center space-x-2.5">
                        <LockIcon className="w-4 h-4 text-sky-400 flex-shrink-0" />
                        <div>
                            <div className="text-xs font-semibold text-slate-200">
                                Pessimistic Row Lock
                            </div>
                            <div className="text-[10px] text-slate-400 font-mono">
                                SELECT ... FOR UPDATE with sorted UUIDs
                            </div>
                        </div>
                    </div>
                    <span className={`inline-flex items-center space-x-1 text-[11px] font-medium px-2 py-0.5 rounded border ${
                        lockStatus === 'enforced'
                            ? 'text-sky-400 bg-sky-500/10 border-sky-500/20'
                            : 'text-amber-400 bg-amber-500/10 border-amber-500/20'
                    }`}>
                        <CheckCircleIcon className="w-3 h-3" />
                        <span>{lockStatus === 'enforced' ? 'ACID Safe' : 'Releasing'}</span>
                    </span>
                </div>

                {/* PostgreSQL Idempotency Traps */}
                <div className="p-3 rounded-xl bg-slate-900/80 border border-white/[0.06] flex items-center justify-between">
                    <div className="flex items-center space-x-2.5">
                        <TerminalIcon className="w-4 h-4 text-blue-400 flex-shrink-0" />
                        <div>
                            <div className="text-xs font-semibold text-slate-200">
                                Idempotency Traps
                            </div>
                            <div className="text-[10px] text-slate-400 font-mono">
                                Database unique constraint & replay control
                            </div>
                        </div>
                    </div>
                    <span className={`inline-flex items-center space-x-1 text-[11px] font-medium px-2 py-0.5 rounded border ${
                        idempotencyStatus === 'active'
                            ? 'text-blue-400 bg-blue-500/10 border-blue-500/20'
                            : 'text-amber-400 bg-amber-500/10 border-amber-500/20'
                    }`}>
                        <CheckCircleIcon className="w-3 h-3" />
                        <span>{idempotencyStatus === 'active' ? 'Locked' : 'Degraded'}</span>
                    </span>
                </div>
            </div>

            {/* Non-Flashing Security Alerts Stream */}
            <div className="space-y-2">
                <div className="flex items-center justify-between text-xs text-slate-400 font-medium">
                    <span>Audit Events & Signals</span>
                    <span className="font-mono text-[10px] text-slate-400">
                        {defaultAlerts.length} Recorded
                    </span>
                </div>

                <div className="space-y-2">
                    {defaultAlerts.map((alert) => {
                        const style = getSeverityBadge(alert.severity);
                        return (
                            <div
                                key={alert.id}
                                className={`p-2.5 rounded-xl border ${style.badge} flex items-start space-x-2 text-xs`}
                            >
                                <span className="font-bold flex-shrink-0 mt-0.5 font-mono">
                                    {style.prefix}
                                </span>
                                <div className="flex-1 min-w-0">
                                    <div className="font-semibold truncate">{alert.title}</div>
                                    <p className="text-[11px] opacity-90 leading-tight mt-0.5">
                                        {alert.message}
                                    </p>
                                    {alert.code && (
                                        <div className="text-[9px] font-mono opacity-70 mt-1">
                                            {alert.code} · {alert.timestamp}
                                        </div>
                                    )}
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>
        </GlassCard>
    );
};
