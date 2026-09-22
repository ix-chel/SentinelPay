<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SentinelPay — Institutional Payment Intelligence</title>
    <meta name="description" content="SentinelPay secure payment operations dashboard. Real-time transaction ledger, ACID-grade transfer integrity, and HMAC-signed API access.">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    <!-- Styles / Scripts -->
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <link rel="stylesheet" href="/css/app.css">
    @endif
</head>
<body>

<!-- ══════════════════════════════════════════════════════════════════════════
     LOGIN SCREEN
     ══════════════════════════════════════════════════════════════════════════ -->
<div id="login-screen" style="position:fixed;inset:0;z-index:500;background:#020617;display:flex;align-items:center;justify-content:center;padding:24px;">
    <div style="width:100%;max-width:400px;">
        <!-- Logo -->
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:36px;justify-content:center;">
            <div style="width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,#2563EB,#1D4ED8);display:flex;align-items:center;justify-content:center;box-shadow:0 4px 16px rgba(37,99,235,0.4);">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                </svg>
            </div>
            <div>
                <div style="font-size:18px;font-weight:700;color:#F8FAFC;letter-spacing:-0.01em;">SentinelPay</div>
                <div style="font-size:11px;color:#475569;font-weight:500;letter-spacing:0.06em;text-transform:uppercase;">Operations Console</div>
            </div>
        </div>

        <!-- Card -->
        <div class="sp-card" style="padding:32px;">
            <h1 style="font-size:22px;font-weight:700;color:#F8FAFC;margin-bottom:6px;letter-spacing:-0.02em;">Sign in</h1>
            <p style="font-size:13.5px;color:#64748B;margin-bottom:28px;">Access your payment operations dashboard.</p>

            <div id="login-error" style="display:none;background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.25);border-radius:10px;padding:10px 14px;font-size:13px;color:#F87171;margin-bottom:18px;"></div>

            <form id="login-form" onsubmit="return false;" autocomplete="on">
                <div style="margin-bottom:16px;">
                    <label for="login-email" style="display:block;font-size:12px;font-weight:600;color:#94A3B8;margin-bottom:7px;letter-spacing:0.04em;text-transform:uppercase;">Email</label>
                    <input id="login-email" type="email" class="sp-input" placeholder="operator@sentinelpay.io" autocomplete="email" required>
                </div>
                <div style="margin-bottom:24px;">
                    <label for="login-password" style="display:block;font-size:12px;font-weight:600;color:#94A3B8;margin-bottom:7px;letter-spacing:0.04em;text-transform:uppercase;">Password</label>
                    <input id="login-password" type="password" class="sp-input" placeholder="••••••••" autocomplete="current-password" required>
                </div>

                <!-- HMAC Secret (demo config) -->
                <div style="margin-bottom:24px;padding:16px;background:rgba(37,99,235,0.06);border:1px solid rgba(37,99,235,0.18);border-radius:12px;">
                    <div style="display:flex;align-items:center;gap:7px;margin-bottom:10px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#60A5FA" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <span style="font-size:11.5px;font-weight:600;color:#60A5FA;text-transform:uppercase;letter-spacing:0.06em;">HMAC Transfer Secret</span>
                    </div>
                    <input id="login-hmac" type="password" class="sp-input" placeholder="Enter HMAC secret for signing transfers" autocomplete="off">
                    <p style="font-size:11px;color:#475569;margin-top:8px;">Stored in session memory only — never persisted to disk or localStorage.</p>
                </div>

                <button id="login-btn" type="submit" class="btn-primary" style="width:100%;padding:12px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                    Sign In
                </button>
            </form>
        </div>

        <div style="text-align:center;margin-top:20px;font-size:12px;color:#334155;">
            SentinelPay v1.0 · ACID-grade · HMAC-SHA256
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     MAIN APP SHELL  (hidden until authenticated)
     ══════════════════════════════════════════════════════════════════════════ -->
<div id="app-shell" style="display:none;height:100dvh;flex-direction:row;overflow:hidden;">

    <!-- Mobile Backdrop -->
    <div id="mobile-backdrop" onclick="window.SP.closeMobileMenu()"></div>

    <!-- ─── SIDEBAR ─────────────────────────────────────────────────────── -->
    <aside id="sidebar" style="display:flex;flex-direction:column;height:100dvh;background:#0A1020;border-right:1px solid rgba(255,255,255,0.06);">

        <!-- Logo -->
        <div style="padding:16px 12px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid rgba(255,255,255,0.05);">
            <div style="display:flex;align-items:center;gap:9px;min-width:0;">
                <div style="width:32px;height:32px;flex-shrink:0;border-radius:8px;background:linear-gradient(135deg,#2563EB,#1D4ED8);display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(37,99,235,0.4);">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    </svg>
                </div>
                <span class="logo-text" style="font-size:14px;font-weight:700;color:#F8FAFC;letter-spacing:-0.01em;">SentinelPay</span>
            </div>
            <button id="sidebar-toggle" class="btn-ghost" style="padding:5px;width:28px;height:28px;flex-shrink:0;" aria-label="Toggle sidebar" title="Toggle sidebar">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
        </div>

        <!-- Navigation -->
        <nav style="flex:1;padding:12px 8px;overflow-y:auto;" aria-label="Main navigation">
            <div class="sidebar-section-label" style="font-size:10px;font-weight:600;color:#334155;text-transform:uppercase;letter-spacing:0.08em;padding:6px 12px 4px;">Operations</div>

            <button class="nav-item active" id="nav-overview" onclick="window.SP.navigate('overview')" aria-label="Overview dashboard">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                <span class="nav-label">Overview</span>
            </button>

            <button class="nav-item" id="nav-transactions" onclick="window.SP.navigate('transactions')" aria-label="Transaction ledger">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                <span class="nav-label">Transactions</span>
            </button>

            <button class="nav-item" id="nav-transfer" onclick="window.SP.navigate('transfer')" aria-label="New transfer">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>
                <span class="nav-label">Transfer</span>
            </button>

            <div class="sidebar-section-label" style="font-size:10px;font-weight:600;color:#334155;text-transform:uppercase;letter-spacing:0.08em;padding:14px 12px 4px;">Infrastructure</div>

            <button class="nav-item" id="nav-system" onclick="window.SP.navigate('system')" aria-label="System topology">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"/><rect x="2" y="14" width="20" height="8" rx="2" ry="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>
                <span class="nav-label">System</span>
            </button>
        </nav>

        <!-- User Profile -->
        <div style="padding:10px 8px;border-top:1px solid rgba(255,255,255,0.05);">
            <div style="display:flex;align-items:center;gap:9px;padding:8px 12px;border-radius:10px;">
                <div id="user-avatar" style="width:28px;height:28px;flex-shrink:0;border-radius:50%;background:linear-gradient(135deg,#2563EB,#7C3AED);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:white;">?</div>
                <div class="nav-label" style="min-width:0;flex:1;">
                    <div id="user-name-sidebar" style="font-size:12.5px;font-weight:600;color:#CBD5E1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">—</div>
                    <div id="user-email-sidebar" style="font-size:11px;color:#475569;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">—</div>
                </div>
                <button onclick="window.SP.logout()" class="btn-ghost" style="padding:4px;width:26px;height:26px;flex-shrink:0;" title="Sign out" aria-label="Sign out">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                </button>
            </div>
        </div>
    </aside>

    <!-- ─── MAIN AREA ──────────────────────────────────────────────────── -->
    <div style="flex:1;display:flex;flex-direction:column;min-width:0;overflow:hidden;">

        <!-- Topbar -->
        <header class="glass-elevated" style="padding:0 20px;height:56px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid rgba(255,255,255,0.06);flex-shrink:0;">
            <div style="display:flex;align-items:center;gap:12px;">
                <!-- Mobile menu -->
                <button id="mobile-menu-btn" class="btn-ghost" style="padding:6px;display:none;" onclick="window.SP.openMobileMenu()" aria-label="Open menu">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>

                <!-- Search trigger -->
                <button id="search-trigger" class="btn-ghost" style="gap:8px;color:#475569;font-size:13px;min-width:180px;justify-content:flex-start;" onclick="window.SP.openCommandPalette()" aria-label="Open command palette (Cmd+K)">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    Search...
                    <span style="margin-left:auto;font-size:10px;background:rgba(255,255,255,0.07);padding:2px 6px;border-radius:5px;color:#334155;">⌘K</span>
                </button>
            </div>

            <div style="display:flex;align-items:center;gap:10px;">
                <!-- Health status -->
                <div id="health-indicator" style="display:flex;align-items:center;gap:6px;padding:5px 12px;border-radius:8px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.07);">
                    <span class="status-dot pending" id="health-dot"></span>
                    <span id="health-text" style="font-size:12px;color:#64748B;font-weight:500;">Checking...</span>
                </div>

                <!-- Account selector -->
                <select id="account-selector" class="sp-select" style="width:auto;min-width:180px;font-size:13px;padding:6px 32px 6px 12px;" aria-label="Select account">
                    <option value="">Loading accounts...</option>
                </select>

                <!-- New Transfer CTA -->
                <button class="btn-primary" onclick="window.SP.navigate('transfer')" id="new-transfer-btn" aria-label="Initiate new transfer">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    New Transfer
                </button>
            </div>
        </header>

        <!-- Content Area -->
        <main id="content-area" style="flex:1;overflow-y:auto;padding:24px;" aria-live="polite">

            <!-- ══ VIEW: OVERVIEW ══════════════════════════════════════════ -->
            <div id="view-overview" class="sp-view">
                <!-- KPI Cards -->
                <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px;" id="kpi-grid">
                    <!-- Vault Balance -->
                    <div class="metric-card glass-institutional">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
                            <span style="font-size:11px;font-weight:600;color:#475569;text-transform:uppercase;letter-spacing:0.06em;">Vault Balance</span>
                            <div style="width:32px;height:32px;border-radius:9px;background:rgba(37,99,235,0.15);display:flex;align-items:center;justify-content:center;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#60A5FA" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            </div>
                        </div>
                        <div id="kpi-balance" class="tabular-nums" style="font-size:26px;font-weight:700;color:#F8FAFC;letter-spacing:-0.02em;line-height:1;">
                            <div class="skeleton" style="height:32px;width:140px;"></div>
                        </div>
                        <div id="kpi-balance-sub" style="font-size:12px;color:#475569;margin-top:6px;">Select an account above</div>
                    </div>

                    <!-- 24h Volume -->
                    <div class="metric-card glass-institutional">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
                            <span style="font-size:11px;font-weight:600;color:#475569;text-transform:uppercase;letter-spacing:0.06em;">24h Volume</span>
                            <div style="width:32px;height:32px;border-radius:9px;background:rgba(56,189,248,0.12);display:flex;align-items:center;justify-content:center;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#38BDF8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                            </div>
                        </div>
                        <div id="kpi-volume" class="tabular-nums" style="font-size:26px;font-weight:700;color:#F8FAFC;letter-spacing:-0.02em;line-height:1;">
                            <div class="skeleton" style="height:32px;width:120px;"></div>
                        </div>
                        <div id="kpi-volume-sub" style="font-size:12px;color:#475569;margin-top:6px;">Calculating...</div>
                    </div>

                    <!-- ACID Integrity -->
                    <div class="metric-card glass-institutional">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
                            <span style="font-size:11px;font-weight:600;color:#475569;text-transform:uppercase;letter-spacing:0.06em;">ACID Integrity</span>
                            <div style="width:32px;height:32px;border-radius:9px;background:rgba(34,197,94,0.12);display:flex;align-items:center;justify-content:center;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#22C55E" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            </div>
                        </div>
                        <div style="font-size:26px;font-weight:700;color:#4ADE80;letter-spacing:-0.02em;line-height:1;">100%</div>
                        <div style="font-size:12px;color:#475569;margin-top:6px;">PostgreSQL pessimistic locks</div>
                    </div>

                    <!-- API Latency -->
                    <div class="metric-card glass-institutional">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
                            <span style="font-size:11px;font-weight:600;color:#475569;text-transform:uppercase;letter-spacing:0.06em;">API Health</span>
                            <div style="width:32px;height:32px;border-radius:9px;background:rgba(245,158,11,0.12);display:flex;align-items:center;justify-content:center;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#F59E0B" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            </div>
                        </div>
                        <div id="kpi-latency" class="tabular-nums" style="font-size:26px;font-weight:700;color:#F8FAFC;letter-spacing:-0.02em;line-height:1;">—</div>
                        <div id="kpi-latency-sub" style="font-size:12px;color:#475569;margin-top:6px;">Avg. response time</div>
                    </div>
                </div>

                <!-- Main Grid: Ledger + Security HUD -->
                <div style="display:grid;grid-template-columns:1fr 340px;gap:16px;align-items:start;">
                    <!-- Transaction Ledger -->
                    <div class="sp-card" style="overflow:hidden;">
                        <div style="padding:18px 20px 14px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid rgba(255,255,255,0.05);">
                            <div style="display:flex;align-items:center;gap:10px;">
                                <span style="font-size:14px;font-weight:700;color:#F8FAFC;">Transaction Ledger</span>
                                <div id="ledger-live-dot" style="display:flex;align-items:center;gap:5px;">
                                    <span class="status-dot pending"></span>
                                    <span style="font-size:11px;color:#475569;">Live</span>
                                </div>
                            </div>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <button id="ledger-refresh-btn" class="btn-ghost" style="padding:6px 10px;font-size:12px;" onclick="window.SP.refreshLedger()" aria-label="Refresh ledger">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                                    Refresh
                                </button>
                            </div>
                        </div>

                        <!-- Buffered update pill -->
                        <div id="ledger-new-pill" style="display:none;padding:8px 20px;background:rgba(37,99,235,0.08);border-bottom:1px solid rgba(37,99,235,0.15);text-align:center;">
                            <button onclick="window.SP.loadNewTransactions()" style="font-size:12px;color:#60A5FA;font-weight:600;background:none;border:none;cursor:pointer;">
                                <span id="ledger-new-count">0</span> new transactions — click to view
                            </button>
                        </div>

                        <div id="ledger-overview-wrap" style="overflow-x:auto;max-height:420px;overflow-y:auto;">
                            <table class="ledger-table" aria-label="Transaction ledger">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody id="ledger-overview-body">
                                    <tr><td colspan="5" style="text-align:center;color:#334155;padding:32px;">
                                        <div style="margin-bottom:8px;">
                                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#334155" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                                        </div>
                                        Select an account to load transactions
                                    </td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Security HUD -->
                    <div style="display:flex;flex-direction:column;gap:12px;">
                        <!-- API Health Card -->
                        <div class="sp-card" style="padding:18px;">
                            <div style="font-size:12px;font-weight:600;color:#475569;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:14px;">Security HUD</div>

                            <div style="display:flex;flex-direction:column;gap:10px;">
                                <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;background:rgba(255,255,255,0.03);border-radius:9px;border:1px solid rgba(255,255,255,0.05);">
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#60A5FA" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                        <span style="font-size:12.5px;color:#94A3B8;">HMAC Verification</span>
                                    </div>
                                    <span class="badge badge-active">Active</span>
                                </div>

                                <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;background:rgba(255,255,255,0.03);border-radius:9px;border:1px solid rgba(255,255,255,0.05);">
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#4ADE80" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                        <span style="font-size:12.5px;color:#94A3B8;">Pessimistic Locks</span>
                                    </div>
                                    <span class="badge badge-success">Enabled</span>
                                </div>

                                <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;background:rgba(255,255,255,0.03);border-radius:9px;border:1px solid rgba(255,255,255,0.05);">
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#FCD34D" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                                        <span style="font-size:12.5px;color:#94A3B8;">Idempotency Guard</span>
                                    </div>
                                    <span class="badge badge-active">Active</span>
                                </div>

                                <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;background:rgba(255,255,255,0.03);border-radius:9px;border:1px solid rgba(255,255,255,0.05);">
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#94A3B8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                        <span style="font-size:12.5px;color:#94A3B8;">Rate Limiting</span>
                                    </div>
                                    <span class="badge badge-active">30/min</span>
                                </div>
                            </div>
                        </div>

                        <!-- Risk Alerts -->
                        <div class="sp-card" style="padding:18px;">
                            <div style="font-size:12px;font-weight:600;color:#475569;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:12px;">Risk Monitor</div>
                            <div id="risk-alerts-list">
                                <div style="display:flex;align-items:center;gap:8px;padding:10px;background:rgba(34,197,94,0.05);border:1px solid rgba(34,197,94,0.12);border-radius:9px;">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#22C55E" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                    <span style="font-size:12.5px;color:#4ADE80;font-weight:500;">All clear — No active alerts</span>
                                </div>
                            </div>
                        </div>

                        <!-- Session Info -->
                        <div class="sp-card" style="padding:14px 18px;">
                            <div style="font-size:11px;font-weight:600;color:#334155;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:10px;">Session</div>
                            <div style="display:flex;flex-direction:column;gap:6px;">
                                <div style="display:flex;justify-content:space-between;">
                                    <span style="font-size:12px;color:#475569;">User</span>
                                    <span id="hud-user" style="font-size:12px;color:#94A3B8;font-weight:500;">—</span>
                                </div>
                                <div style="display:flex;justify-content:space-between;">
                                    <span style="font-size:12px;color:#475569;">HMAC Secret</span>
                                    <span id="hud-hmac" style="font-size:12px;color:#94A3B8;font-weight:500;">—</span>
                                </div>
                                <div style="display:flex;justify-content:space-between;">
                                    <span style="font-size:12px;color:#475569;">Token</span>
                                    <span id="hud-token" style="font-size:12px;color:#4ADE80;font-weight:500;">—</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══ VIEW: TRANSACTIONS ══════════════════════════════════════ -->
            <div id="view-transactions" class="sp-view" style="display:none;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
                    <div>
                        <h1 style="font-size:22px;font-weight:700;color:#F8FAFC;letter-spacing:-0.02em;">Transaction Ledger</h1>
                        <p style="font-size:13px;color:#475569;margin-top:3px;">Full transaction history for the selected account.</p>
                    </div>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <select id="tx-filter-status" class="sp-select" style="width:auto;font-size:13px;padding:7px 32px 7px 12px;" onchange="window.SP.filterTransactions()" aria-label="Filter by status">
                            <option value="">All Status</option>
                            <option value="completed">Completed</option>
                            <option value="pending">Pending</option>
                            <option value="failed">Failed</option>
                        </select>
                        <button class="btn-ghost" onclick="window.SP.refreshLedger()" aria-label="Refresh transactions">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                            Refresh
                        </button>
                    </div>
                </div>

                <div class="sp-card" style="overflow:hidden;">
                    <div style="overflow-x:auto;">
                        <table class="ledger-table" aria-label="Full transaction history">
                            <thead>
                                <tr>
                                    <th>Transaction ID</th>
                                    <th>Sender</th>
                                    <th>Receiver</th>
                                    <th>Amount</th>
                                    <th>Currency</th>
                                    <th>Status</th>
                                    <th>Idempotency Key</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody id="ledger-full-body">
                                <tr><td colspan="8" style="text-align:center;color:#334155;padding:40px;">Select an account to load transactions.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <!-- Pagination -->
                    <div id="tx-pagination" style="padding:14px 20px;border-top:1px solid rgba(255,255,255,0.05);display:flex;align-items:center;justify-content:space-between;">
                        <span id="tx-page-info" style="font-size:12px;color:#475569;">—</span>
                        <div style="display:flex;gap:6px;">
                            <button id="tx-prev-btn" class="btn-ghost" style="padding:6px 12px;font-size:12px;" onclick="window.SP.txPrevPage()" disabled aria-label="Previous page">← Prev</button>
                            <button id="tx-next-btn" class="btn-ghost" style="padding:6px 12px;font-size:12px;" onclick="window.SP.txNextPage()" disabled aria-label="Next page">Next →</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══ VIEW: TRANSFER ════════════════════════════════════════ -->
            <div id="view-transfer" class="sp-view" style="display:none;">
                <div style="max-width:560px;margin:0 auto;">
                    <div style="margin-bottom:24px;">
                        <h1 style="font-size:22px;font-weight:700;color:#F8FAFC;letter-spacing:-0.02em;">New Transfer</h1>
                        <p style="font-size:13px;color:#475569;margin-top:3px;">Initiate an ACID-grade, HMAC-signed fund transfer.</p>
                    </div>

                    <!-- Step indicator -->
                    <div class="step-indicator" style="margin-bottom:28px;" aria-label="Transfer steps">
                        <div class="step-dot active" id="step-dot-1" aria-label="Step 1: Draft">1</div>
                        <div class="step-line"></div>
                        <div class="step-dot pending" id="step-dot-2" aria-label="Step 2: Review">2</div>
                        <div class="step-line"></div>
                        <div class="step-dot pending" id="step-dot-3" aria-label="Step 3: Submit">3</div>
                        <div class="step-line"></div>
                        <div class="step-dot pending" id="step-dot-4" aria-label="Step 4: Result">4</div>
                    </div>

                    <!-- Step 1: Draft -->
                    <div id="transfer-step-1" class="sp-card" style="padding:28px;">
                        <h2 style="font-size:16px;font-weight:700;color:#F8FAFC;margin-bottom:4px;">Transfer Details</h2>
                        <p style="font-size:12.5px;color:#475569;margin-bottom:24px;">Enter the transfer parameters. An idempotency key is generated automatically.</p>

                        <div style="display:flex;flex-direction:column;gap:16px;">
                            <div>
                                <label for="tf-sender" style="display:block;font-size:12px;font-weight:600;color:#94A3B8;margin-bottom:7px;text-transform:uppercase;letter-spacing:0.04em;">Sender Account</label>
                                <select id="tf-sender" class="sp-select" aria-label="Sender account">
                                    <option value="">Select sender account...</option>
                                </select>
                            </div>

                            <div>
                                <label for="tf-receiver" style="display:block;font-size:12px;font-weight:600;color:#94A3B8;margin-bottom:7px;text-transform:uppercase;letter-spacing:0.04em;">Receiver Account ID</label>
                                <input id="tf-receiver" type="text" class="sp-input font-mono" placeholder="e.g. 2" aria-label="Receiver account ID">
                            </div>

                            <div style="display:grid;grid-template-columns:1fr 120px;gap:12px;">
                                <div>
                                    <label for="tf-amount" style="display:block;font-size:12px;font-weight:600;color:#94A3B8;margin-bottom:7px;text-transform:uppercase;letter-spacing:0.04em;">Amount</label>
                                    <input id="tf-amount" type="number" class="sp-input tabular-nums" placeholder="0.00" min="0.01" step="0.01" aria-label="Transfer amount">
                                </div>
                                <div>
                                    <label for="tf-currency" style="display:block;font-size:12px;font-weight:600;color:#94A3B8;margin-bottom:7px;text-transform:uppercase;letter-spacing:0.04em;">Currency</label>
                                    <select id="tf-currency" class="sp-select" aria-label="Transfer currency">
                                        <option value="USD">USD</option>
                                        <option value="EUR">EUR</option>
                                        <option value="GBP">GBP</option>
                                        <option value="IDR">IDR</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Idempotency Key -->
                            <div>
                                <label style="display:block;font-size:12px;font-weight:600;color:#94A3B8;margin-bottom:7px;text-transform:uppercase;letter-spacing:0.04em;">Idempotency Key</label>
                                <div style="display:flex;gap:8px;">
                                    <input id="tf-idem-key" type="text" class="sp-input font-mono" style="font-size:12px;" readonly aria-label="Idempotency key (auto-generated)">
                                    <button class="btn-ghost" style="flex-shrink:0;" onclick="window.SP.regenIdempotencyKey()" title="Regenerate key" aria-label="Regenerate idempotency key">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                                    </button>
                                </div>
                                <p style="font-size:11px;color:#334155;margin-top:6px;">Auto-generated via <code style="background:rgba(255,255,255,0.05);padding:1px 5px;border-radius:4px;font-size:10px;">crypto.randomUUID()</code> — ensures exactly-once execution.</p>
                            </div>
                        </div>

                        <div id="transfer-draft-error" style="display:none;margin-top:16px;padding:10px 14px;background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.25);border-radius:9px;font-size:13px;color:#F87171;"></div>

                        <button class="btn-primary" style="width:100%;margin-top:24px;" onclick="window.SP.transferReview()" aria-label="Review transfer details">
                            Review Transfer →
                        </button>
                    </div>

                    <!-- Step 2: Review -->
                    <div id="transfer-step-2" class="sp-card" style="padding:28px;display:none;">
                        <h2 style="font-size:16px;font-weight:700;color:#F8FAFC;margin-bottom:4px;">Review & Confirm</h2>
                        <p style="font-size:12.5px;color:#475569;margin-bottom:24px;">Verify all details before signing and submitting.</p>

                        <div id="transfer-review-details" style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.07);border-radius:12px;padding:18px;display:flex;flex-direction:column;gap:12px;margin-bottom:20px;">
                        </div>

                        <!-- HMAC preview -->
                        <div style="background:rgba(37,99,235,0.05);border:1px solid rgba(37,99,235,0.15);border-radius:12px;padding:14px;margin-bottom:20px;">
                            <div style="display:flex;align-items:center;gap:7px;margin-bottom:8px;">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#60A5FA" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                <span style="font-size:11.5px;font-weight:700;color:#60A5FA;text-transform:uppercase;letter-spacing:0.06em;">HMAC-SHA256 Signature</span>
                            </div>
                            <div id="hmac-preview" class="font-mono" style="font-size:10.5px;color:#475569;word-break:break-all;line-height:1.6;">Computing...</div>
                        </div>

                        <div style="display:flex;gap:10px;">
                            <button class="btn-ghost" style="flex:1;" onclick="window.SP.transferBack()" aria-label="Go back to draft">← Back</button>
                            <button class="btn-primary" style="flex:2;" onclick="window.SP.transferSubmit()" id="transfer-confirm-btn" aria-label="Confirm and submit transfer">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                Confirm & Submit
                            </button>
                        </div>
                    </div>

                    <!-- Step 3: Processing -->
                    <div id="transfer-step-3" style="display:none;">
                        <div class="sp-card" style="padding:40px 28px;text-align:center;">
                            <div id="transfer-processing-icon" style="width:56px;height:56px;border-radius:50%;background:rgba(37,99,235,0.15);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                                <svg id="processing-spinner" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#60A5FA" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="animation:spin 1s linear infinite;">
                                    <polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
                                </svg>
                            </div>
                            <div id="transfer-processing-steps" style="display:flex;flex-direction:column;gap:8px;margin-bottom:20px;text-align:left;background:rgba(255,255,255,0.03);border-radius:12px;padding:16px;border:1px solid rgba(255,255,255,0.06);">
                            </div>
                            <p id="transfer-processing-label" style="font-size:14px;font-weight:600;color:#94A3B8;">Submitting transfer...</p>
                        </div>
                    </div>

                    <!-- Step 4: Result -->
                    <div id="transfer-step-4" style="display:none;">
                        <div class="sp-card" style="padding:28px;" id="transfer-result-card">
                        </div>
                        <button class="btn-ghost" style="width:100%;margin-top:12px;" onclick="window.SP.transferReset()" aria-label="Start a new transfer">
                            New Transfer
                        </button>
                    </div>
                </div>
            </div>

            <!-- ══ VIEW: SYSTEM ═══════════════════════════════════════════ -->
            <div id="view-system" class="sp-view" style="display:none;">
                <div style="margin-bottom:24px;">
                    <h1 style="font-size:22px;font-weight:700;color:#F8FAFC;letter-spacing:-0.02em;">System Topology</h1>
                    <p style="font-size:13px;color:#475569;margin-top:3px;">Infrastructure status and component health.</p>
                </div>

                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:20px;">
                    <!-- Laravel API -->
                    <div class="sp-card" style="padding:20px;">
                        <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:16px;">
                            <div style="width:40px;height:40px;border-radius:10px;background:rgba(239,68,68,0.12);display:flex;align-items:center;justify-content:center;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#F87171" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
                            </div>
                            <span id="sys-laravel-badge" class="badge badge-pending">Checking</span>
                        </div>
                        <div style="font-size:15px;font-weight:700;color:#F8FAFC;margin-bottom:4px;">Laravel 12 API</div>
                        <div style="font-size:12px;color:#475569;margin-bottom:14px;">REST API engine · Sanctum auth · HMAC middleware</div>
                        <div style="display:flex;flex-direction:column;gap:6px;">
                            <div style="display:flex;justify-content:space-between;font-size:11.5px;">
                                <span style="color:#475569;">PHP</span><span style="color:#94A3B8;">8.2+</span>
                            </div>
                            <div style="display:flex;justify-content:space-between;font-size:11.5px;">
                                <span style="color:#475569;">Auth</span><span style="color:#94A3B8;">Sanctum Bearer</span>
                            </div>
                            <div style="display:flex;justify-content:space-between;font-size:11.5px;">
                                <span style="color:#475569;">Signing</span><span style="color:#94A3B8;">HMAC-SHA256</span>
                            </div>
                        </div>
                    </div>

                    <!-- PostgreSQL -->
                    <div class="sp-card" style="padding:20px;">
                        <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:16px;">
                            <div style="width:40px;height:40px;border-radius:10px;background:rgba(56,189,248,0.12);display:flex;align-items:center;justify-content:center;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#38BDF8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
                            </div>
                            <span class="badge badge-success">Active</span>
                        </div>
                        <div style="font-size:15px;font-weight:700;color:#F8FAFC;margin-bottom:4px;">PostgreSQL</div>
                        <div style="font-size:12px;color:#475569;margin-bottom:14px;">ACID-grade transactions · Pessimistic row locking</div>
                        <div style="display:flex;flex-direction:column;gap:6px;">
                            <div style="display:flex;justify-content:space-between;font-size:11.5px;">
                                <span style="color:#475569;">Engine</span><span style="color:#94A3B8;">PostgreSQL 14+</span>
                            </div>
                            <div style="display:flex;justify-content:space-between;font-size:11.5px;">
                                <span style="color:#475569;">Locking</span><span style="color:#94A3B8;">lockForUpdate()</span>
                            </div>
                            <div style="display:flex;justify-content:space-between;font-size:11.5px;">
                                <span style="color:#475569;">Isolation</span><span style="color:#94A3B8;">Read Committed</span>
                            </div>
                        </div>
                    </div>

                    <!-- Queue / Sync -->
                    <div class="sp-card" style="padding:20px;">
                        <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:16px;">
                            <div style="width:40px;height:40px;border-radius:10px;background:rgba(245,158,11,0.12);display:flex;align-items:center;justify-content:center;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#F59E0B" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            </div>
                            <span class="badge badge-active">Sync</span>
                        </div>
                        <div style="font-size:15px;font-weight:700;color:#F8FAFC;margin-bottom:4px;">Queue Worker</div>
                        <div style="font-size:12px;color:#475569;margin-bottom:14px;">Synchronous processing · Local dev mode</div>
                        <div style="display:flex;flex-direction:column;gap:6px;">
                            <div style="display:flex;justify-content:space-between;font-size:11.5px;">
                                <span style="color:#475569;">Driver</span><span style="color:#94A3B8;">sync</span>
                            </div>
                            <div style="display:flex;justify-content:space-between;font-size:11.5px;">
                                <span style="color:#475569;">RabbitMQ</span><span style="color:#94A3B8;">Phase 4 (deferred)</span>
                            </div>
                        </div>
                    </div>

                    <!-- File Cache -->
                    <div class="sp-card" style="padding:20px;">
                        <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:16px;">
                            <div style="width:40px;height:40px;border-radius:10px;background:rgba(168,85,247,0.12);display:flex;align-items:center;justify-content:center;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#C084FC" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                            </div>
                            <span class="badge badge-active">File</span>
                        </div>
                        <div style="font-size:15px;font-weight:700;color:#F8FAFC;margin-bottom:4px;">Cache Store</div>
                        <div style="font-size:12px;color:#475569;margin-bottom:14px;">File-based caching · Local dev mode</div>
                        <div style="display:flex;flex-direction:column;gap:6px;">
                            <div style="display:flex;justify-content:space-between;font-size:11.5px;">
                                <span style="color:#475569;">Driver</span><span style="color:#94A3B8;">file</span>
                            </div>
                            <div style="display:flex;justify-content:space-between;font-size:11.5px;">
                                <span style="color:#475569;">Redis</span><span style="color:#94A3B8;">Phase 5 (deferred)</span>
                            </div>
                        </div>
                    </div>

                    <!-- Rate Limiter -->
                    <div class="sp-card" style="padding:20px;">
                        <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:16px;">
                            <div style="width:40px;height:40px;border-radius:10px;background:rgba(34,197,94,0.12);display:flex;align-items:center;justify-content:center;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#22C55E" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            </div>
                            <span class="badge badge-success">Active</span>
                        </div>
                        <div style="font-size:15px;font-weight:700;color:#F8FAFC;margin-bottom:4px;">Rate Limiter</div>
                        <div style="font-size:12px;color:#475569;margin-bottom:14px;">Per-IP throttling via Laravel middleware</div>
                        <div style="display:flex;flex-direction:column;gap:6px;">
                            <div style="display:flex;justify-content:space-between;font-size:11.5px;">
                                <span style="color:#475569;">Login</span><span style="color:#94A3B8;">5/min</span>
                            </div>
                            <div style="display:flex;justify-content:space-between;font-size:11.5px;">
                                <span style="color:#475569;">Transfers</span><span style="color:#94A3B8;">30/min</span>
                            </div>
                            <div style="display:flex;justify-content:space-between;font-size:11.5px;">
                                <span style="color:#475569;">Health</span><span style="color:#94A3B8;">120/min</span>
                            </div>
                        </div>
                    </div>

                    <!-- API Endpoints -->
                    <div class="sp-card" style="padding:20px;">
                        <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:16px;">
                            <div style="width:40px;height:40px;border-radius:10px;background:rgba(37,99,235,0.12);display:flex;align-items:center;justify-content:center;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#60A5FA" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                            </div>
                            <span id="sys-api-badge" class="badge badge-pending">Checking</span>
                        </div>
                        <div style="font-size:15px;font-weight:700;color:#F8FAFC;margin-bottom:4px;">API Endpoints</div>
                        <div style="font-size:12px;color:#475569;margin-bottom:14px;">v1 REST endpoints</div>
                        <div style="display:flex;flex-direction:column;gap:6px;">
                            <div style="display:flex;justify-content:space-between;font-size:11.5px;">
                                <span style="color:#475569;">GET /health</span><span id="sys-health-status" style="color:#94A3B8;">—</span>
                            </div>
                            <div style="display:flex;justify-content:space-between;font-size:11.5px;">
                                <span style="color:#475569;">POST /transfers</span><span style="color:#94A3B8;">HMAC</span>
                            </div>
                            <div style="display:flex;justify-content:space-between;font-size:11.5px;">
                                <span style="color:#475569;">GET /balance</span><span style="color:#94A3B8;">Bearer</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- API base info -->
                <div class="sp-card" style="padding:18px 22px;">
                    <div style="font-size:12px;font-weight:600;color:#334155;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:12px;">Configuration</div>
                    <div style="display:flex;flex-direction:column;gap:8px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;background:rgba(255,255,255,0.025);border-radius:8px;">
                            <span style="font-size:12.5px;color:#475569;">API Base URL</span>
                            <code id="sys-api-url" class="font-mono" style="font-size:12px;color:#94A3B8;background:rgba(255,255,255,0.05);padding:3px 8px;border-radius:5px;">http://localhost:8000/api/v1</code>
                        </div>
                        <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;background:rgba(255,255,255,0.025);border-radius:8px;">
                            <span style="font-size:12.5px;color:#475569;">HMAC Algorithm</span>
                            <code class="font-mono" style="font-size:12px;color:#94A3B8;background:rgba(255,255,255,0.05);padding:3px 8px;border-radius:5px;">HMAC-SHA256</code>
                        </div>
                        <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;background:rgba(255,255,255,0.025);border-radius:8px;">
                            <span style="font-size:12.5px;color:#475569;">HMAC Secret Loaded</span>
                            <span id="sys-hmac-loaded" style="font-size:12px;color:#94A3B8;font-weight:600;">—</span>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     COMMAND PALETTE
     ══════════════════════════════════════════════════════════════════════════ -->
<div id="command-palette" role="dialog" aria-modal="true" aria-label="Command palette">
    <div class="palette-box">
        <div style="display:flex;align-items:center;gap:10px;padding:14px 16px;border-bottom:1px solid rgba(255,255,255,0.07);">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#475569" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input id="palette-input" type="text" placeholder="Search views, accounts..." style="flex:1;background:transparent;border:none;outline:none;color:#F8FAFC;font-size:15px;font-family:inherit;" oninput="window.SP.palettSearch(this.value)" aria-label="Command palette search">
            <button onclick="window.SP.closeCommandPalette()" class="btn-ghost" style="padding:4px 8px;font-size:11px;" aria-label="Close palette">ESC</button>
        </div>
        <div id="palette-results" style="padding:8px;max-height:320px;overflow-y:auto;" role="listbox">
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     TOAST CONTAINER
     ══════════════════════════════════════════════════════════════════════════ -->
<div id="toast-container" role="status" aria-live="polite"></div>

<!-- ══════════════════════════════════════════════════════════════════════════
     SPIN KEYFRAME (used for processing spinner)
     ══════════════════════════════════════════════════════════════════════════ -->
<style>
    @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    .sp-view { animation: fadeIn 0.2s ease; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: none; } }

    @media (max-width: 768px) {
        #mobile-menu-btn { display: flex !important; }
        #search-trigger { min-width: 120px; }
        #kpi-grid { grid-template-columns: 1fr 1fr !important; }
        #view-overview > div:last-child { grid-template-columns: 1fr !important; }
        #view-system > div:first-of-type { grid-template-columns: 1fr 1fr !important; }
    }
    @media (max-width: 480px) {
        #kpi-grid { grid-template-columns: 1fr !important; }
        #view-system > div:first-of-type { grid-template-columns: 1fr !important; }
        #new-transfer-btn .btn-label { display: none; }
    }
</style>

</body>
</html>
