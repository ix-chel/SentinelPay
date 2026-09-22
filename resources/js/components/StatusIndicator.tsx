import React from 'react';
import { CheckCircleIcon, ClockIcon, XCircleIcon, ActivityIcon } from './Icons';

export interface StatusIndicatorProps {
    label: string;
    status: 'connected' | 'healthy' | 'deferred' | 'pending' | 'error';
    detail: string;
}

export const StatusIndicator: React.FC<StatusIndicatorProps> = ({ label, status, detail }) => {
    const statusConfig = {
        connected: {
            dot: 'bg-emerald-400 shadow-[0_0_8px_rgba(52,211,153,0.5)]',
            badge: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
            icon: CheckCircleIcon,
            text: 'Connected',
        },
        healthy: {
            dot: 'bg-emerald-400 shadow-[0_0_8px_rgba(52,211,153,0.5)]',
            badge: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
            icon: CheckCircleIcon,
            text: 'Healthy',
        },
        deferred: {
            dot: 'bg-amber-400 shadow-[0_0_8px_rgba(251,191,36,0.4)]',
            badge: 'bg-amber-500/10 text-amber-400 border-amber-500/20',
            icon: ClockIcon,
            text: 'Deferred',
        },
        pending: {
            dot: 'bg-sky-400 animate-pulse',
            badge: 'bg-sky-500/10 text-sky-400 border-sky-500/20',
            icon: ActivityIcon,
            text: 'Pending',
        },
        error: {
            dot: 'bg-rose-400 shadow-[0_0_8px_rgba(251,113,133,0.5)]',
            badge: 'bg-rose-500/10 text-rose-400 border-rose-500/20',
            icon: XCircleIcon,
            text: 'Degraded',
        },
    };

    const config = statusConfig[status] || statusConfig.pending;
    const IconComponent = config.icon;

    return (
        <div className="flex items-center justify-between p-4 rounded-xl border border-white/[0.08] bg-[#0F172A] hover:bg-[#1E293B]/60 hover:border-white/[0.12] transition-all duration-200">
            <div className="flex items-start space-x-3 min-w-0">
                <span className={`w-2.5 h-2.5 rounded-full mt-1.5 flex-shrink-0 ${config.dot}`} aria-hidden="true" />
                <div className="min-w-0">
                    <div className="text-sm font-semibold text-slate-100 truncate">{label}</div>
                    <div className="text-xs font-mono text-slate-400 truncate mt-0.5">{detail}</div>
                </div>
            </div>
            <span
                className={`inline-flex items-center space-x-1 text-xs px-2.5 py-1 rounded-full border font-medium flex-shrink-0 ml-3 ${config.badge}`}
                aria-label={`Status: ${config.text}`}
            >
                <IconComponent className="w-3 h-3" />
                <span>{config.text}</span>
            </span>
        </div>
    );
};
