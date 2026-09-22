import React from 'react';
import { SearchIcon, MenuIcon, SendIcon } from './Icons';

interface HeaderProps {
    title: string;
    onOpenSearch: () => void;
    onOpenTransfer: () => void;
    onOpenMobileNav: () => void;
    isHealthy: boolean;
    serviceName: string;
}

export const Header: React.FC<HeaderProps> = ({
    title,
    onOpenSearch,
    onOpenTransfer,
    onOpenMobileNav,
    isHealthy,
    serviceName,
}) => {
    return (
        <header className="glass-header sticky top-0 z-20 h-16 px-4 sm:px-6 flex items-center justify-between">
            {/* Left: Mobile Toggle & Page Breadcrumb */}
            <div className="flex items-center space-x-3">
                <button
                    onClick={onOpenMobileNav}
                    className="lg:hidden p-2 rounded-xl text-slate-400 hover:text-white hover:bg-white/[0.06] transition-colors"
                    aria-label="Open navigation menu"
                >
                    <MenuIcon className="w-5 h-5" />
                </button>
                <div>
                    <h1 className="text-base sm:text-lg font-bold text-slate-100 tracking-tight">
                        {title}
                    </h1>
                </div>
            </div>

            {/* Center: Command Palette Trigger */}
            <div className="hidden md:flex items-center max-w-sm w-full mx-4">
                <button
                    onClick={onOpenSearch}
                    className="w-full flex items-center justify-between px-3.5 py-1.5 rounded-xl bg-slate-900/90 border border-white/[0.08] hover:border-white/[0.15] text-slate-400 text-xs transition-all duration-150 group shadow-inner"
                    aria-label="Open command palette"
                >
                    <span className="flex items-center space-x-2">
                        <SearchIcon className="w-3.5 h-3.5 text-slate-500 group-hover:text-slate-300" />
                        <span>Search transactions, accounts, or keys...</span>
                    </span>
                    <kbd className="hidden sm:inline-block px-1.5 py-0.5 text-[10px] font-mono bg-white/[0.06] text-slate-400 border border-white/[0.08] rounded">
                        ⌘K
                    </kbd>
                </button>
            </div>

            {/* Right: Health Status & Primary CTA */}
            <div className="flex items-center space-x-3">
                {/* Live System Health Badge */}
                <div
                    className={`hidden sm:inline-flex items-center space-x-1.5 px-3 py-1 rounded-full text-xs font-medium border ${
                        isHealthy
                            ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20'
                            : 'bg-rose-500/10 text-rose-400 border-rose-500/20'
                    }`}
                    title={`Service: ${serviceName}`}
                >
                    <span
                        className={`w-2 h-2 rounded-full ${
                            isHealthy
                                ? 'bg-emerald-400 shadow-[0_0_6px_rgba(52,211,153,0.6)] animate-pulse'
                                : 'bg-rose-400'
                        }`}
                        aria-hidden="true"
                    />
                    <span className="hidden md:inline font-mono">
                        {isHealthy ? `${serviceName} Online` : 'Degraded'}
                    </span>
                </div>

                {/* Primary Action CTA: New Transfer */}
                <button
                    onClick={onOpenTransfer}
                    className="inline-flex items-center space-x-2 px-3.5 py-1.5 rounded-xl bg-blue-600 hover:bg-blue-500 active:bg-blue-700 text-white text-xs font-semibold shadow-lg shadow-blue-600/25 transition-all duration-150 focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:outline-none"
                >
                    <SendIcon className="w-3.5 h-3.5" />
                    <span>New Transfer</span>
                    <kbd className="hidden lg:inline-block text-[10px] opacity-75 font-mono px-1 py-0.2 rounded bg-black/20">
                        T
                    </kbd>
                </button>
            </div>
        </header>
    );
};
