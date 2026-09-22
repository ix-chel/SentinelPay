import React, { useState, useEffect } from 'react';
import type { NavigationTab } from './Sidebar';
import {
    SearchIcon,
    ShieldIcon,
    LayersIcon,
    ArrowUpRightIcon,
    ActivityIcon,
    ServerIcon,
    SendIcon,
    XIcon,
} from './Icons';

interface CommandPaletteProps {
    isOpen: boolean;
    onClose: () => void;
    onSelectTab: (tab: NavigationTab) => void;
    onOpenTransfer: () => void;
}

interface CommandItem {
    id: string;
    title: string;
    subtitle: string;
    icon: React.FC<{ className?: string }>;
    action: () => void;
    shortcut?: string;
}

export const CommandPalette: React.FC<CommandPaletteProps> = ({
    isOpen,
    onClose,
    onSelectTab,
    onOpenTransfer,
}) => {
    const [query, setQuery] = useState('');
    const [selectedIndex, setSelectedIndex] = useState(0);

    const commands: CommandItem[] = [
        {
            id: 'transfer',
            title: 'Initiate New Fund Transfer',
            subtitle: 'Execute idempotent transfer with pessimistic row locking',
            icon: SendIcon,
            action: () => {
                onOpenTransfer();
                onClose();
            },
            shortcut: 'T',
        },
        {
            id: 'nav-overview',
            title: 'Go to FinOps Overview',
            subtitle: 'Vault balance, settled volume, and live metrics',
            icon: ShieldIcon,
            action: () => {
                onSelectTab('overview');
                onClose();
            },
        },
        {
            id: 'nav-ledger',
            title: 'Go to Authoritative Ledger',
            subtitle: 'Inspect immutable double-entry transaction records',
            icon: LayersIcon,
            action: () => {
                onSelectTab('ledger');
                onClose();
            },
        },
        {
            id: 'nav-transfers',
            title: 'Go to Transfer Engine',
            subtitle: 'Execute and test fund movements',
            icon: ArrowUpRightIcon,
            action: () => {
                onSelectTab('transfers');
                onClose();
            },
        },
        {
            id: 'nav-security',
            title: 'Go to Security & Integrity HUD',
            subtitle: 'Review HMAC verification, locks, and risk signals',
            icon: ActivityIcon,
            action: () => {
                onSelectTab('security');
                onClose();
            },
        },
        {
            id: 'nav-topology',
            title: 'Go to System Topology',
            subtitle: 'Inspect Laravel 12 API, PostgreSQL, and cache infrastructure',
            icon: ServerIcon,
            action: () => {
                onSelectTab('topology');
                onClose();
            },
        },
    ];

    const filtered = commands.filter((cmd) =>
        cmd.title.toLowerCase().includes(query.toLowerCase()) ||
        cmd.subtitle.toLowerCase().includes(query.toLowerCase())
    );

    useEffect(() => {
        setSelectedIndex(0);
    }, [query]);

    useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            if (!isOpen) return;

            if (e.key === 'Escape') {
                onClose();
            } else if (e.key === 'ArrowDown') {
                e.preventDefault();
                setSelectedIndex((prev) => (prev + 1) % (filtered.length || 1));
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                setSelectedIndex((prev) => (prev - 1 + filtered.length) % (filtered.length || 1));
            } else if (e.key === 'Enter' && filtered[selectedIndex]) {
                e.preventDefault();
                filtered[selectedIndex].action();
            }
        };

        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [isOpen, selectedIndex, filtered, onClose]);

    if (!isOpen) return null;

    return (
        <div
            className="fixed inset-0 z-50 flex items-start justify-center pt-20 px-4"
            role="dialog"
            aria-modal="true"
            aria-label="Command Search"
        >
            <div
                className="fixed inset-0 bg-black/75 backdrop-blur-sm transition-opacity"
                onClick={onClose}
                aria-hidden="true"
            />

            <div className="relative w-full max-w-lg glass-modal rounded-2xl overflow-hidden z-10 text-slate-200 shadow-2xl">
                {/* Search Input Bar */}
                <div className="flex items-center px-4 py-3.5 border-b border-white/[0.08] bg-slate-900/90">
                    <SearchIcon className="w-4 h-4 text-slate-400 mr-3 flex-shrink-0" />
                    <input
                        type="text"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Search commands, views, or actions..."
                        className="w-full bg-transparent text-sm text-slate-100 placeholder-slate-500 focus:outline-none"
                        autoFocus
                    />
                    <button
                        onClick={onClose}
                        className="p-1 rounded text-slate-500 hover:text-white"
                        aria-label="Close search"
                    >
                        <XIcon className="w-4 h-4" />
                    </button>
                </div>

                {/* Command Results */}
                <div className="p-2 max-h-80 overflow-y-auto space-y-1">
                    {filtered.length === 0 ? (
                        <div className="py-8 text-center text-xs text-slate-500">
                            No matching commands found.
                        </div>
                    ) : (
                        filtered.map((cmd, idx) => {
                            const Icon = cmd.icon;
                            const isSelected = idx === selectedIndex;
                            return (
                                <button
                                    key={cmd.id}
                                    onClick={cmd.action}
                                    onMouseEnter={() => setSelectedIndex(idx)}
                                    className={`w-full flex items-center justify-between p-2.5 rounded-xl text-left transition-colors ${
                                        isSelected
                                            ? 'bg-blue-600/20 text-blue-300 border border-blue-500/30'
                                            : 'text-slate-300 hover:bg-white/[0.04] border border-transparent'
                                    }`}
                                >
                                    <div className="flex items-center space-x-3 min-w-0">
                                        <div
                                            className={`p-2 rounded-lg ${
                                                isSelected
                                                    ? 'bg-blue-500/20 text-blue-400'
                                                    : 'bg-slate-800 text-slate-400'
                                            }`}
                                        >
                                            <Icon className="w-4 h-4" />
                                        </div>
                                        <div className="min-w-0">
                                            <div className="text-xs font-semibold text-slate-100 truncate">
                                                {cmd.title}
                                            </div>
                                            <div className="text-[11px] text-slate-400 truncate">
                                                {cmd.subtitle}
                                            </div>
                                        </div>
                                    </div>
                                    {cmd.shortcut && (
                                        <kbd className="px-1.5 py-0.5 text-[10px] font-mono bg-white/[0.08] text-slate-300 border border-white/[0.1] rounded ml-2">
                                            {cmd.shortcut}
                                        </kbd>
                                    )}
                                </button>
                            );
                        })
                    )}
                </div>

                {/* Footer hint */}
                <div className="px-4 py-2 border-t border-white/[0.06] bg-slate-900/60 text-[10px] text-slate-400 flex items-center justify-between">
                    <span>Navigation: ↑ ↓ to navigate, Enter to select, Esc to exit</span>
                    <span className="font-mono">SentinelPay Command OS</span>
                </div>
            </div>
        </div>
    );
};
