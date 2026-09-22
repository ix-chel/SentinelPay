import React, { useState } from 'react';
import { Sidebar, type NavigationTab } from '../components/Sidebar';
import { Header } from '../components/Header';

interface AppLayoutProps {
    children: React.ReactNode;
    currentTab: NavigationTab;
    onSelectTab: (tab: NavigationTab) => void;
    onOpenSearch: () => void;
    onOpenTransfer: () => void;
    isHealthy: boolean;
    serviceName?: string;
}

export const AppLayout: React.FC<AppLayoutProps> = ({
    children,
    currentTab,
    onSelectTab,
    onOpenSearch,
    onOpenTransfer,
    isHealthy,
    serviceName = 'SentinelPay',
}) => {
    const [isCollapsed, setIsCollapsed] = useState(false);
    const [mobileOpen, setMobileOpen] = useState(false);

    const tabTitles: Record<NavigationTab, string> = {
        overview: 'FinOps Overview',
        ledger: 'Authoritative Ledger',
        transfers: 'Transfer Engine',
        security: 'Security & Integrity HUD',
        topology: 'Architecture Topology',
    };

    return (
        <div className="flex h-screen bg-[#020617] text-[#F8FAFC] overflow-hidden antialiased font-sans">
            {/* Persistent Sidebar (Desktop) + Glass Drawer (Mobile) */}
            <Sidebar
                currentTab={currentTab}
                onSelectTab={onSelectTab}
                isCollapsed={isCollapsed}
                onToggleCollapse={() => setIsCollapsed(!isCollapsed)}
                mobileOpen={mobileOpen}
                onCloseMobile={() => setMobileOpen(false)}
            />

            {/* Main Application Area */}
            <div className="flex flex-col flex-1 min-w-0 overflow-hidden">
                {/* Command Bar Header */}
                <Header
                    title={tabTitles[currentTab]}
                    onOpenSearch={onOpenSearch}
                    onOpenTransfer={onOpenTransfer}
                    onOpenMobileNav={() => setMobileOpen(true)}
                    isHealthy={isHealthy}
                    serviceName={serviceName}
                />

                {/* Scrollable Viewport */}
                <main className="flex-1 overflow-y-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
                    {children}

                    {/* Institutional Footer */}
                    <footer className="pt-8 pb-4 text-center text-xs text-slate-500 border-t border-white/[0.04]">
                        SentinelPay 2.0 · Local Native Architecture · Laravel 12 + PostgreSQL ACID + React 19
                    </footer>
                </main>
            </div>
        </div>
    );
};
