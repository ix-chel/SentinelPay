import React from 'react';
import {
    ShieldIcon,
    LayersIcon,
    ArrowUpRightIcon,
    ActivityIcon,
    ServerIcon,
    ChevronRightIcon,
    XIcon,
    LockIcon,
} from './Icons';

export type NavigationTab = 'overview' | 'ledger' | 'transfers' | 'security' | 'topology';

interface SidebarProps {
    currentTab: NavigationTab;
    onSelectTab: (tab: NavigationTab) => void;
    isCollapsed: boolean;
    onToggleCollapse: () => void;
    mobileOpen: boolean;
    onCloseMobile: () => void;
}

export const Sidebar: React.FC<SidebarProps> = ({
    currentTab,
    onSelectTab,
    isCollapsed,
    onToggleCollapse,
    mobileOpen,
    onCloseMobile,
}) => {
    const navItems: { id: NavigationTab; label: string; icon: React.FC<{ className?: string }> }[] = [
        { id: 'overview', label: 'Overview', icon: ShieldIcon },
        { id: 'ledger', label: 'Authoritative Ledger', icon: LayersIcon },
        { id: 'transfers', label: 'Transfer Execution', icon: ArrowUpRightIcon },
        { id: 'security', label: 'Security & Risk HUD', icon: ActivityIcon },
        { id: 'topology', label: 'System Topology', icon: ServerIcon },
    ];

    const sidebarContent = (
        <div className="flex flex-col h-full bg-[#0F172A] border-r border-white/[0.08] text-slate-300">
            {/* Brand Header */}
            <div className="h-16 flex items-center justify-between px-4 border-b border-white/[0.08]">
                <div className="flex items-center space-x-3 overflow-hidden">
                    <div className="w-9 h-9 rounded-xl bg-gradient-to-br from-blue-600 to-sky-500 flex items-center justify-center text-white shadow-lg shadow-blue-500/20 flex-shrink-0">
                        <ShieldIcon className="w-5 h-5 text-white" />
                    </div>
                    {(!isCollapsed || mobileOpen) && (
                        <div className="min-w-0">
                            <div className="font-bold text-slate-100 text-sm tracking-tight flex items-center space-x-1.5 truncate">
                                <span>SentinelPay</span>
                                <span className="text-[10px] uppercase font-mono px-1.5 py-0.5 rounded bg-blue-500/10 text-blue-400 border border-blue-500/20">
                                    v2.0
                                </span>
                            </div>
                            <div className="text-[11px] text-slate-400 truncate">
                                High-Availability FinOps
                            </div>
                        </div>
                    )}
                </div>

                {mobileOpen ? (
                    <button
                        onClick={onCloseMobile}
                        className="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/[0.06] transition-colors"
                        aria-label="Close menu"
                    >
                        <XIcon className="w-5 h-5" />
                    </button>
                ) : (
                    <button
                        onClick={onToggleCollapse}
                        className="hidden lg:flex p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/[0.06] transition-colors"
                        aria-label={isCollapsed ? 'Expand sidebar' : 'Collapse sidebar'}
                    >
                        <ChevronRightIcon
                            className={`w-4 h-4 transition-transform duration-200 ${
                                isCollapsed ? '' : 'rotate-180'
                            }`}
                        />
                    </button>
                )}
            </div>

            {/* Navigation List */}
            <nav className="flex-1 py-4 px-2 space-y-1 overflow-y-auto">
                {navItems.map((item) => {
                    const Icon = item.icon;
                    const isActive = currentTab === item.id;
                    return (
                        <button
                            key={item.id}
                            onClick={() => {
                                onSelectTab(item.id);
                                if (mobileOpen) onCloseMobile();
                            }}
                            className={`w-full flex items-center space-x-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 text-left ${
                                isActive
                                    ? 'bg-blue-600/15 text-blue-400 border border-blue-500/30 shadow-sm'
                                    : 'text-slate-400 hover:text-slate-200 hover:bg-white/[0.04] border border-transparent'
                            }`}
                            title={isCollapsed && !mobileOpen ? item.label : undefined}
                            aria-current={isActive ? 'page' : undefined}
                        >
                            <Icon
                                className={`w-4 h-4 flex-shrink-0 ${
                                    isActive ? 'text-blue-400' : 'text-slate-400'
                                }`}
                            />
                            {(!isCollapsed || mobileOpen) && (
                                <span className="truncate">{item.label}</span>
                            )}
                        </button>
                    );
                })}
            </nav>

            {/* Bottom Guardrail Indicator */}
            {(!isCollapsed || mobileOpen) && (
                <div className="p-3 m-3 rounded-xl bg-slate-900/80 border border-white/[0.06] text-xs text-slate-400 space-y-1.5">
                    <div className="flex items-center justify-between font-semibold text-slate-300">
                        <span className="flex items-center space-x-1.5">
                            <LockIcon className="w-3.5 h-3.5 text-emerald-400" />
                            <span>Pessimistic Locking</span>
                        </span>
                        <span className="text-[10px] text-emerald-400 font-mono">ACID</span>
                    </div>
                    <p className="text-[11px] text-slate-400 leading-snug">
                        PostgreSQL row locks prevent concurrent race conditions.
                    </p>
                </div>
            )}
        </div>
    );

    return (
        <>
            {/* Desktop Persistent Sidebar */}
            <aside
                className={`hidden lg:block flex-shrink-0 transition-all duration-200 z-30 ${
                    isCollapsed ? 'w-20' : 'w-64'
                }`}
                aria-label="Desktop Navigation"
            >
                {sidebarContent}
            </aside>

            {/* Mobile Glass Drawer Overlay */}
            {mobileOpen && (
                <div
                    className="fixed inset-0 z-50 lg:hidden flex"
                    role="dialog"
                    aria-modal="true"
                    aria-label="Mobile Navigation Drawer"
                >
                    <div
                        className="fixed inset-0 bg-black/70 backdrop-blur-sm transition-opacity"
                        onClick={onCloseMobile}
                        aria-hidden="true"
                    />
                    <div className="relative w-72 max-w-[85vw] h-full shadow-2xl glass-modal border-r border-white/10 z-10">
                        {sidebarContent}
                    </div>
                </div>
            )}
        </>
    );
};
