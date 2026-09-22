import React from 'react';
import { GlassCard } from './GlassCard';

interface MetricCardProps {
    title: string;
    value: string;
    subtitle?: string;
    trend?: {
        label: string;
        direction: 'up' | 'down' | 'neutral';
    };
    icon: React.FC<{ className?: string }>;
    accentColor?: 'blue' | 'emerald' | 'amber' | 'purple';
    badgeText?: string;
}

export const MetricCard: React.FC<MetricCardProps> = ({
    title,
    value,
    subtitle,
    trend,
    icon: Icon,
    accentColor = 'blue',
    badgeText,
}) => {
    const accentStyles = {
        blue: 'text-blue-400 bg-blue-500/10 border-blue-500/20',
        emerald: 'text-emerald-400 bg-emerald-500/10 border-emerald-500/20',
        amber: 'text-amber-400 bg-amber-500/10 border-amber-500/20',
        purple: 'text-purple-400 bg-purple-500/10 border-purple-500/20',
    };

    return (
        <GlassCard variant="default" className="p-5 flex flex-col justify-between overflow-hidden">
            <div className="flex items-start justify-between">
                <div className="space-y-1">
                    <span className="text-xs font-medium uppercase tracking-wider text-slate-400">
                        {title}
                    </span>
                    <div className="text-2xl sm:text-3xl font-bold tracking-tight text-slate-100 tabular-nums">
                        {value}
                    </div>
                </div>
                <div className={`p-2.5 rounded-xl border ${accentStyles[accentColor]} flex-shrink-0`}>
                    <Icon className="w-5 h-5" />
                </div>
            </div>

            {(subtitle || trend || badgeText) && (
                <div className="mt-4 pt-3 border-t border-white/[0.06] flex items-center justify-between text-xs">
                    {subtitle && <span className="text-slate-400 truncate">{subtitle}</span>}

                    {trend && (
                        <span
                            className={`inline-flex items-center space-x-1 font-medium font-mono text-[11px] ${
                                trend.direction === 'up'
                                    ? 'text-emerald-400'
                                    : trend.direction === 'down'
                                    ? 'text-rose-400'
                                    : 'text-slate-400'
                            }`}
                        >
                            <span>{trend.direction === 'up' ? '↑' : trend.direction === 'down' ? '↓' : '→'}</span>
                            <span>{trend.label}</span>
                        </span>
                    )}

                    {badgeText && (
                        <span className="px-2 py-0.5 rounded-full text-[10px] font-mono bg-white/[0.06] text-slate-300 border border-white/[0.08]">
                            {badgeText}
                        </span>
                    )}
                </div>
            )}
        </GlassCard>
    );
};
