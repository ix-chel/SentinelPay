/**
 * SentinelPay — Institutional Dashboard Engine
 * Vanilla ES module — no runtime dependencies.
 *
 * Responsibilities:
 *   - Auth state (login / logout / Sanctum token)
 *   - Navigation (view switching)
 *   - API layer (typed fetch wrappers, HMAC-SHA256 signing)
 *   - Ledger rendering (transactions table, buffered updates)
 *   - Transfer flow state machine (Draft → Review → Submit → Result)
 *   - Security HUD (live health polling)
 *   - Command Palette (Cmd+K)
 *   - Toast notification system
 */

'use strict';

/* ─── Constants ─────────────────────────────────────────────────────────── */
const API_BASE = '/api/v1';

/* ─── App State ─────────────────────────────────────────────────────────── */
const state = {
    token: null,            // Sanctum Bearer token (in-memory only)
    hmacSecret: null,       // HMAC secret (session-scoped, never persisted)
    user: null,             // { id, name, email }
    currentView: 'overview',
    accounts: [],           // loaded from balance queries
    selectedAccountId: null,
    transactions: [],       // cached for current account
    txCurrentPage: 1,
    txTotalPages: 1,
    txStatusFilter: '',
    healthLatency: null,
    healthPollTimer: null,
    newTxBuffer: [],        // buffered new transactions while user is reading
};

/* ─── Utils ─────────────────────────────────────────────────────────────── */
const $ = (id) => document.getElementById(id);
const fmt = (n, curr = 'USD') =>
    new Intl.NumberFormat('en-US', { style: 'currency', currency: curr, minimumFractionDigits: 2 }).format(n);
const fmtDate = (iso) => {
    const d = new Date(iso);
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) +
        ' ' + d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
};
const maskKey = (k) => k ? k.slice(0, 4) + '••••' + k.slice(-4) : '—';

/* ─── Toast ─────────────────────────────────────────────────────────────── */
const TOAST_ICONS = {
    success: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#4ADE80" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>',
    error:   '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#F87171" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
    info:    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#60A5FA" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
    warning: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#FCD34D" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
};

function toast(message, type = 'info', duration = 4000) {
    const container = $('toast-container');
    const el = document.createElement('div');
    el.className = `toast toast-${type}`;
    el.innerHTML = `${TOAST_ICONS[type] || ''}<span>${message}</span>`;
    el.addEventListener('click', () => removeToast(el));
    container.appendChild(el);
    if (duration > 0) setTimeout(() => removeToast(el), duration);
}

function removeToast(el) {
    el.classList.add('removing');
    el.addEventListener('animationend', () => el.remove(), { once: true });
}

/* ─── HMAC Signing (Web Crypto API — no secrets in source) ──────────────── */
async function hmacSign(bodyString) {
    if (!state.hmacSecret) return null;
    const encoder = new TextEncoder();
    const keyData = encoder.encode(state.hmacSecret);
    const key = await crypto.subtle.importKey(
        'raw', keyData, { name: 'HMAC', hash: 'SHA-256' }, false, ['sign']
    );
    const sig = await crypto.subtle.sign('HMAC', key, encoder.encode(bodyString));
    return Array.from(new Uint8Array(sig)).map(b => b.toString(16).padStart(2, '0')).join('');
}

/* ─── API Layer ─────────────────────────────────────────────────────────── */
async function apiFetch(path, { method = 'GET', body = null, auth = false, hmac = false } = {}) {
    const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };

    if (auth && state.token) headers['Authorization'] = `Bearer ${state.token}`;

    let bodyStr = null;
    if (body) {
        bodyStr = JSON.stringify(body);
        if (hmac) {
            const sig = await hmacSign(bodyStr);
            if (!sig) throw new Error('HMAC secret not configured. Please set it on the login screen.');
            headers['X-Signature'] = sig;
        }
    }

    const start = performance.now();
    const res = await fetch(`${API_BASE}${path}`, {
        method,
        headers,
        body: bodyStr,
    });
    const latency = Math.round(performance.now() - start);

    if (path === '/health') state.healthLatency = latency;

    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        const err = new Error(data.message || `HTTP ${res.status}`);
        err.code = data.error;
        err.status = res.status;
        throw err;
    }
    return data;
}

/* ─── Health Polling ─────────────────────────────────────────────────────── */
async function pollHealth() {
    try {
        const start = performance.now();
        await apiFetch('/health');
        const ms = Math.round(performance.now() - start);
        state.healthLatency = ms;

        setHealthStatus('online', `${ms}ms`);
        updateKpiLatency(ms);
        updateSysHealthStatus('ok');
    } catch {
        setHealthStatus('offline', 'Unreachable');
        updateSysHealthStatus('offline');
    }
}

function setHealthStatus(status, label) {
    const dot = $('health-dot');
    const text = $('health-text');
    if (dot) { dot.className = `status-dot ${status}`; }
    if (text) {
        text.textContent = status === 'online' ? `API ${label}` : label;
        text.style.color = status === 'online' ? '#4ADE80' : '#F87171';
    }
}

function updateKpiLatency(ms) {
    const el = $('kpi-latency');
    const sub = $('kpi-latency-sub');
    if (el) { el.textContent = `${ms}ms`; el.style.color = ms < 200 ? '#4ADE80' : ms < 500 ? '#FCD34D' : '#F87171'; }
    if (sub) sub.textContent = ms < 200 ? 'Excellent response' : ms < 500 ? 'Moderate latency' : 'High latency';
}

function updateSysHealthStatus(status) {
    const badge = $('sys-laravel-badge');
    const apiBadge = $('sys-api-badge');
    const sysStatus = $('sys-health-status');

    if (badge) { badge.className = `badge ${status === 'ok' ? 'badge-success' : 'badge-failed'}`; badge.textContent = status === 'ok' ? 'Healthy' : 'Offline'; }
    if (apiBadge) { apiBadge.className = `badge ${status === 'ok' ? 'badge-success' : 'badge-failed'}`; apiBadge.textContent = status === 'ok' ? 'Online' : 'Offline'; }
    if (sysStatus) { sysStatus.textContent = status === 'ok' ? '✓ Online' : '✗ Offline'; sysStatus.style.color = status === 'ok' ? '#4ADE80' : '#F87171'; }
}

function startHealthPolling() {
    pollHealth();
    state.healthPollTimer = setInterval(pollHealth, 30000);
}

/* ─── Auth ───────────────────────────────────────────────────────────────── */
async function login() {
    const email = $('login-email').value.trim();
    const password = $('login-password').value;
    const hmac = $('login-hmac').value.trim();
    const errEl = $('login-error');
    const btn = $('login-btn');

    if (!email || !password) { showLoginError('Please enter your email and password.'); return; }

    btn.disabled = true;
    btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="animation:spin 1s linear infinite"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg> Signing in...';
    hideLoginError();

    try {
        const data = await apiFetch('/auth/login', { method: 'POST', body: { email, password } });
        state.token = data.data.token;
        state.user = data.data.user;
        state.hmacSecret = hmac || null;

        onAuthenticated();
    } catch (err) {
        showLoginError(err.message || 'Authentication failed. Check credentials.');
        btn.disabled = false;
        btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg> Sign In';
    }
}

function showLoginError(msg) {
    const el = $('login-error');
    el.textContent = msg;
    el.style.display = 'block';
}
function hideLoginError() {
    const el = $('login-error');
    if (el) el.style.display = 'none';
}

function onAuthenticated() {
    // Hide login, show app
    $('login-screen').style.display = 'none';
    const shell = $('app-shell');
    shell.style.display = 'flex';

    // Populate user info
    const initials = state.user.name.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase();
    $('user-avatar').textContent = initials;
    $('user-name-sidebar').textContent = state.user.name;
    $('user-email-sidebar').textContent = state.user.email;
    $('hud-user').textContent = state.user.name;
    $('hud-token').textContent = 'Active';
    $('hud-hmac').textContent = state.hmacSecret ? maskKey(state.hmacSecret) : 'Not set';
    $('sys-hmac-loaded').textContent = state.hmacSecret ? '✓ Configured' : '✗ Not set';
    $('sys-hmac-loaded').style.color = state.hmacSecret ? '#4ADE80' : '#F87171';

    // Boot systems
    startHealthPolling();
    loadAccounts();
    navigate('overview');
    generateIdempotencyKey();

    toast(`Welcome, ${state.user.name}`, 'success');
}

function logout() {
    if (state.token) {
        apiFetch('/auth/logout', { method: 'POST', auth: true }).catch(() => {});
    }
    clearInterval(state.healthPollTimer);
    Object.assign(state, { token: null, hmacSecret: null, user: null, accounts: [], selectedAccountId: null, transactions: [], newTxBuffer: [] });

    $('app-shell').style.display = 'none';
    $('login-screen').style.display = 'flex';
    $('login-email').value = '';
    $('login-password').value = '';
    $('login-hmac').value = '';
    toast('Signed out successfully.', 'info');
}

/* ─── Account Loading ─────────────────────────────────────────────────────── */
async function loadAccounts() {
    // We try sequential IDs 1–20 to discover accounts belonging to the user.
    // The API will 403 for accounts that don't belong to the user.
    const discovered = [];
    for (let i = 1; i <= 20; i++) {
        try {
            const data = await apiFetch(`/accounts/${i}/balance`, { auth: true });
            if (data.data) {
                discovered.push({ id: data.data.account_id, balance: data.data.balance, currency: data.data.currency, is_active: data.data.is_active });
            }
        } catch (err) {
            if (err.status === 403 || err.status === 401) continue;
            if (err.status === 404) continue;
            break; // network error — stop
        }
    }
    state.accounts = discovered;

    populateAccountSelectors();
    if (discovered.length > 0) {
        selectAccount(discovered[0].id);
    }
}

function populateAccountSelectors() {
    const sel = $('account-selector');
    const tfSel = $('tf-sender');
    if (!sel) return;

    if (state.accounts.length === 0) {
        sel.innerHTML = '<option value="">No accounts found</option>';
        if (tfSel) tfSel.innerHTML = '<option value="">No accounts found</option>';
        return;
    }

    const opts = state.accounts.map(a =>
        `<option value="${a.id}">${a.id} — ${fmt(a.balance, a.currency)} (${a.currency})${!a.is_active ? ' [inactive]' : ''}</option>`
    ).join('');

    sel.innerHTML = opts;
    if (tfSel) tfSel.innerHTML = opts;
}

function selectAccount(id) {
    state.selectedAccountId = id;
    const acc = state.accounts.find(a => a.id == id);
    if (acc) {
        const el = $('kpi-balance');
        if (el) { el.textContent = fmt(acc.balance, acc.currency); el.classList.add('tabular-nums'); }
        const sub = $('kpi-balance-sub');
        if (sub) sub.textContent = `Account #${acc.id} · ${acc.currency}${!acc.is_active ? ' · Inactive' : ''}`;
    }
    loadTransactions();
}

/* ─── Transactions ────────────────────────────────────────────────────────── */
async function loadTransactions(page = 1) {
    if (!state.selectedAccountId) return;
    state.txCurrentPage = page;

    try {
        const data = await apiFetch(
            `/accounts/${state.selectedAccountId}/transactions?per_page=20&page=${page}`,
            { auth: true }
        );
        state.transactions = data.data?.data || [];
        state.txTotalPages = data.data?.last_page || 1;

        renderLedgerOverview();
        renderLedgerFull();
        updatePagination();

        // Update 24h volume
        const volume = state.transactions
            .filter(tx => {
                const d = new Date(tx.created_at);
                return Date.now() - d.getTime() < 86400000 && tx.status === 'completed';
            })
            .reduce((sum, tx) => sum + parseFloat(tx.amount), 0);

        const volEl = $('kpi-volume');
        if (volEl) volEl.textContent = fmt(volume, 'USD');
        const volSub = $('kpi-volume-sub');
        if (volSub) volSub.textContent = `${state.transactions.filter(t => t.status === 'completed').length} completed transactions`;

    } catch (err) {
        toast('Failed to load transactions: ' + err.message, 'error');
    }
}

function renderLedgerOverview() {
    const tbody = $('ledger-overview-body');
    if (!tbody) return;

    if (state.transactions.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#334155;padding:32px;">No transactions found.</td></tr>';
        return;
    }

    const rows = state.transactions.slice(0, 15).map(tx => {
        const isSender = tx.sender_id == state.selectedAccountId;
        const typeHtml = isSender
            ? `<span style="display:inline-flex;align-items:center;gap:4px;color:#F87171;font-size:12px;font-weight:500;"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="7" y1="17" x2="17" y2="7"/><polyline points="7 7 17 7 17 17"/></svg>Sent</span>`
            : `<span style="display:inline-flex;align-items:center;gap:4px;color:#4ADE80;font-size:12px;font-weight:500;"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="17" y1="7" x2="7" y2="17"/><polyline points="17 17 7 17 7 7"/></svg>Received</span>`;

        return `<tr>
            <td><code class="font-mono" style="font-size:11px;color:#60A5FA;">#${tx.id}</code></td>
            <td>${typeHtml}</td>
            <td class="tabular-nums" style="font-weight:600;color:${isSender ? '#F87171' : '#4ADE80'};">${isSender ? '-' : '+'}${fmt(tx.amount, tx.currency)}</td>
            <td>${statusBadge(tx.status)}</td>
            <td style="font-size:12px;color:#475569;white-space:nowrap;">${fmtDate(tx.created_at)}</td>
        </tr>`;
    }).join('');
    tbody.innerHTML = rows;
}

function renderLedgerFull() {
    const tbody = $('ledger-full-body');
    if (!tbody) return;

    let txs = state.transactions;
    if (state.txStatusFilter) txs = txs.filter(t => t.status === state.txStatusFilter);

    if (txs.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:#334155;padding:40px;">No transactions match the current filter.</td></tr>';
        return;
    }

    tbody.innerHTML = txs.map(tx => `<tr>
        <td>
            <button onclick="copyToClipboard('${tx.id}')" title="Copy ID" style="background:none;border:none;cursor:pointer;padding:2px 4px;border-radius:4px;" aria-label="Copy transaction ID ${tx.id}">
                <code class="font-mono" style="font-size:11px;color:#60A5FA;">#${tx.id}</code>
            </button>
        </td>
        <td class="font-mono" style="font-size:12px;color:#475569;">${tx.sender_id}</td>
        <td class="font-mono" style="font-size:12px;color:#475569;">${tx.receiver_id}</td>
        <td class="tabular-nums" style="font-weight:600;color:#F8FAFC;">${fmt(tx.amount, tx.currency)}</td>
        <td style="font-size:12px;color:#94A3B8;">${tx.currency}</td>
        <td>${statusBadge(tx.status)}</td>
        <td>
            <button onclick="copyToClipboard('${tx.idempotency_key}')" title="Copy key" style="background:none;border:none;cursor:pointer;" aria-label="Copy idempotency key">
                <code class="font-mono" style="font-size:10px;color:#334155;padding:2px 6px;background:rgba(255,255,255,0.04);border-radius:4px;">${tx.idempotency_key ? tx.idempotency_key.slice(0, 8) + '…' : '—'}</code>
            </button>
        </td>
        <td style="font-size:12px;color:#475569;white-space:nowrap;">${fmtDate(tx.created_at)}</td>
    </tr>`).join('');
}

function statusBadge(status) {
    const map = {
        completed: 'badge-success',
        pending:   'badge-pending',
        failed:    'badge-failed',
        conflict:  'badge-conflict',
    };
    const cls = map[status] || 'badge-active';
    const icons = {
        completed: '●',
        pending:   '○',
        failed:    '✕',
        conflict:  '!',
    };
    return `<span class="badge ${cls}">${icons[status] || '?'} ${status}</span>`;
}

function filterTransactions() {
    state.txStatusFilter = $('tx-filter-status').value;
    renderLedgerFull();
}

function updatePagination() {
    $('tx-page-info').textContent = `Page ${state.txCurrentPage} of ${state.txTotalPages}`;
    $('tx-prev-btn').disabled = state.txCurrentPage <= 1;
    $('tx-next-btn').disabled = state.txCurrentPage >= state.txTotalPages;
}

function txPrevPage() { if (state.txCurrentPage > 1) loadTransactions(state.txCurrentPage - 1); }
function txNextPage() { if (state.txCurrentPage < state.txTotalPages) loadTransactions(state.txCurrentPage + 1); }
function refreshLedger() { loadTransactions(state.txCurrentPage); toast('Ledger refreshed', 'info', 2000); }

function loadNewTransactions() {
    $('ledger-new-pill').style.display = 'none';
    state.newTxBuffer = [];
    refreshLedger();
}

/* ─── Navigation ─────────────────────────────────────────────────────────── */
function navigate(viewId) {
    const views = ['overview', 'transactions', 'transfer', 'system'];
    views.forEach(v => {
        const el = $(`view-${v}`);
        if (el) el.style.display = v === viewId ? '' : 'none';
        const navBtn = $(`nav-${v}`);
        if (navBtn) navBtn.classList.toggle('active', v === viewId);
    });
    state.currentView = viewId;

    if (viewId === 'transactions') {
        renderLedgerFull();
    }
    if (viewId === 'transfer') {
        populateAccountSelectors();
        generateIdempotencyKey();
    }
}

/* ─── Transfer Flow State Machine ────────────────────────────────────────── */
function generateIdempotencyKey() {
    const key = crypto.randomUUID();
    const el = $('tf-idem-key');
    if (el) el.value = key;
}

function regenIdempotencyKey() {
    generateIdempotencyKey();
    toast('New idempotency key generated', 'info', 2000);
}

async function transferReview() {
    const errEl = $('transfer-draft-error');
    errEl.style.display = 'none';

    const senderId = $('tf-sender').value;
    const receiverId = $('tf-receiver').value.trim();
    const amount = parseFloat($('tf-amount').value);
    const currency = $('tf-currency').value;
    const idemKey = $('tf-idem-key').value;

    if (!senderId) return showDraftError('Please select a sender account.');
    if (!receiverId) return showDraftError('Please enter a receiver account ID.');
    if (isNaN(amount) || amount <= 0) return showDraftError('Please enter a valid amount greater than 0.');
    if (!idemKey) return showDraftError('Idempotency key missing. Please regenerate.');

    if (!state.hmacSecret) {
        toast('⚠ HMAC secret not configured. Transfer signing will fail.', 'warning', 6000);
    }

    // Show review step
    showStep(2);

    // Build review details
    const detailsEl = $('transfer-review-details');
    const rows = [
        ['Sender Account', `#${senderId}`],
        ['Receiver Account', `#${receiverId}`],
        ['Amount', `${fmt(amount, currency)}`],
        ['Currency', currency],
        ['Idempotency Key', idemKey],
    ];
    detailsEl.innerHTML = rows.map(([label, value]) => `
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <span style="font-size:13px;color:#475569;">${label}</span>
            <span class="font-mono" style="font-size:13px;color:#F8FAFC;font-weight:600;">${value}</span>
        </div>
    `).join('');

    // Compute HMAC preview
    const body = JSON.stringify({
        sender_account_id: parseInt(senderId),
        receiver_account_id: parseInt(receiverId),
        amount,
        currency,
        idempotency_key: idemKey,
    });

    const hmacEl = $('hmac-preview');
    if (state.hmacSecret) {
        try {
            const sig = await hmacSign(body);
            hmacEl.textContent = sig;
            hmacEl.style.color = '#4ADE80';
        } catch {
            hmacEl.textContent = 'Failed to compute signature';
            hmacEl.style.color = '#F87171';
        }
    } else {
        hmacEl.textContent = '⚠ HMAC secret not configured — request will be rejected by the server.';
        hmacEl.style.color = '#FCD34D';
    }
}

function transferBack() { showStep(1); }

async function transferSubmit() {
    const senderId = parseInt($('tf-sender').value);
    const receiverId = parseInt($('tf-receiver').value.trim());
    const amount = parseFloat($('tf-amount').value);
    const currency = $('tf-currency').value;
    const idemKey = $('tf-idem-key').value;

    showStep(3);

    const steps = [
        { label: 'Request Created', status: 'done' },
        { label: 'Idempotency Key Locked', status: 'active' },
        { label: 'Server Signature Verified', status: 'pending' },
        { label: 'Transfer Authorized', status: 'pending' },
    ];

    renderProcessingSteps(steps);

    await sleep(400);
    steps[1].status = 'done';
    steps[2].status = 'active';
    renderProcessingSteps(steps);

    try {
        await sleep(300);
        steps[2].status = 'done';
        steps[3].status = 'active';
        renderProcessingSteps(steps);

        const result = await apiFetch('/transfers', {
            method: 'POST',
            hmac: true,
            body: {
                sender_account_id: senderId,
                receiver_account_id: receiverId,
                amount,
                currency,
                idempotency_key: idemKey,
            },
        });

        steps[3].status = 'done';
        renderProcessingSteps(steps);
        await sleep(400);

        showTransferResult('success', result.data);
        refreshLedger();
        // Refresh balance
        const acc = state.accounts.find(a => a.id == senderId);
        if (acc) {
            const fresh = await apiFetch(`/accounts/${senderId}/balance`, { auth: true });
            if (fresh.data) {
                acc.balance = fresh.data.balance;
                populateAccountSelectors();
                if (state.selectedAccountId == senderId) selectAccount(senderId);
            }
        }
        toast('Transfer completed successfully!', 'success');

    } catch (err) {
        steps[3].status = 'failed';
        renderProcessingSteps(steps);
        await sleep(300);

        if (err.code === 'IDEMPOTENCY_CONFLICT') {
            showTransferResult('conflict', null, err.message);
            toast('Idempotency conflict — existing transaction returned.', 'warning');
        } else {
            showTransferResult('error', null, err.message);
            toast('Transfer failed: ' + err.message, 'error');
        }
    }
}

function renderProcessingSteps(steps) {
    const container = $('transfer-processing-steps');
    const icons = {
        done:    '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#4ADE80" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>',
        active:  '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#60A5FA" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="animation:spin 1s linear infinite"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>',
        pending: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#334155" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/></svg>',
        failed:  '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#F87171" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
    };
    container.innerHTML = steps.map(s => `
        <div style="display:flex;align-items:center;gap:10px;">
            ${icons[s.status] || icons.pending}
            <span style="font-size:13px;color:${s.status === 'done' ? '#4ADE80' : s.status === 'active' ? '#60A5FA' : s.status === 'failed' ? '#F87171' : '#334155'};font-weight:${s.status === 'active' ? '600' : '400'};">${s.label}</span>
        </div>
    `).join('');
}

function showTransferResult(type, data, errMsg) {
    showStep(4);
    const card = $('transfer-result-card');
    if (type === 'success' && data) {
        card.innerHTML = `
            <div style="text-align:center;margin-bottom:24px;">
                <div style="width:56px;height:56px;border-radius:50%;background:rgba(34,197,94,0.15);display:flex;align-items:center;justify-content:center;margin:0 auto 14px;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#22C55E" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <h2 style="font-size:18px;font-weight:700;color:#F8FAFC;margin-bottom:4px;">Transfer Successful</h2>
                <p style="font-size:13px;color:#475569;">Your transfer has been processed and confirmed.</p>
            </div>
            <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.07);border-radius:12px;padding:16px;display:flex;flex-direction:column;gap:10px;">
                ${[
                    ['Transaction ID', `#${data.transaction_id}`],
                    ['Amount', fmt(data.amount, data.currency)],
                    ['Status', data.status],
                    ['Idempotency Key', data.idempotency_key],
                    ['Created At', fmtDate(data.created_at)],
                ].map(([l,v]) => `<div style="display:flex;justify-content:space-between;"><span style="font-size:13px;color:#475569;">${l}</span><span class="font-mono" style="font-size:12px;color:#4ADE80;font-weight:600;">${v}</span></div>`).join('')}
            </div>`;
    } else if (type === 'conflict') {
        card.innerHTML = `
            <div style="text-align:center;margin-bottom:24px;">
                <div style="width:56px;height:56px;border-radius:50%;background:rgba(168,85,247,0.15);display:flex;align-items:center;justify-content:center;margin:0 auto 14px;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#A855F7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                </div>
                <h2 style="font-size:18px;font-weight:700;color:#F8FAFC;margin-bottom:4px;">Idempotency Conflict</h2>
                <p style="font-size:13px;color:#475569;margin-bottom:8px;">${errMsg}</p>
                <p style="font-size:12px;color:#334155;">The original transaction has been preserved. No duplicate charges occurred.</p>
            </div>`;
    } else {
        card.innerHTML = `
            <div style="text-align:center;margin-bottom:20px;">
                <div style="width:56px;height:56px;border-radius:50%;background:rgba(239,68,68,0.15);display:flex;align-items:center;justify-content:center;margin:0 auto 14px;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#EF4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                </div>
                <h2 style="font-size:18px;font-weight:700;color:#F8FAFC;margin-bottom:4px;">Transfer Failed</h2>
                <p style="font-size:13px;color:#F87171;">${errMsg}</p>
            </div>`;
    }
}

function showDraftError(msg) {
    const el = $('transfer-draft-error');
    el.textContent = msg;
    el.style.display = 'block';
}

function showStep(n) {
    [1,2,3,4].forEach(i => {
        const el = $(`transfer-step-${i}`);
        if (el) el.style.display = i === n ? '' : 'none';
    });
    [1,2,3,4].forEach(i => {
        const dot = $(`step-dot-${i}`);
        if (dot) dot.className = `step-dot ${i < n ? 'done' : i === n ? 'active' : 'pending'}`;
    });
    $('transfer-processing-label') && ($('transfer-processing-label').textContent = 'Processing transfer...');
}

function transferReset() {
    showStep(1);
    generateIdempotencyKey();
    $('transfer-draft-error').style.display = 'none';
    $('tf-receiver').value = '';
    $('tf-amount').value = '';
}

function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

/* ─── Command Palette ─────────────────────────────────────────────────────── */
const PALETTE_ITEMS = [
    { label: 'Overview',      icon: '⊞', action: () => navigate('overview') },
    { label: 'Transactions',  icon: '↯', action: () => navigate('transactions') },
    { label: 'New Transfer',  icon: '↓', action: () => navigate('transfer') },
    { label: 'System Status', icon: '⬡', action: () => navigate('system') },
    { label: 'Refresh Ledger', icon: '↺', action: () => refreshLedger() },
    { label: 'Sign Out',      icon: '→', action: () => logout() },
];

function openCommandPalette() {
    $('command-palette').classList.add('open');
    $('palette-input').value = '';
    renderPaletteItems('');
    setTimeout(() => $('palette-input').focus(), 50);
}

function closeCommandPalette() {
    $('command-palette').classList.remove('open');
}

function palettSearch(query) {
    renderPaletteItems(query);
}

function renderPaletteItems(query) {
    const results = $('palette-results');
    const q = query.toLowerCase();
    const items = q
        ? PALETTE_ITEMS.filter(i => i.label.toLowerCase().includes(q))
        : PALETTE_ITEMS;

    if (items.length === 0) {
        results.innerHTML = '<div style="padding:16px 16px;font-size:13px;color:#334155;text-align:center;">No results</div>';
        return;
    }

    results.innerHTML = items.map((item, idx) => `
        <button role="option" class="btn-ghost" style="width:100%;justify-content:flex-start;gap:12px;border:none;padding:10px 14px;border-radius:8px;margin-bottom:2px;"
            onclick="window.SP._paletteAction(${idx}, '${query}')">
            <span style="font-size:16px;width:20px;text-align:center;">${item.icon}</span>
            <span style="font-size:14px;color:#CBD5E1;">${item.label}</span>
        </button>
    `).join('');
}

/* ─── Mobile ──────────────────────────────────────────────────────────────── */
function openMobileMenu() {
    $('sidebar').classList.add('mobile-open');
    $('mobile-backdrop').classList.add('open');
}
function closeMobileMenu() {
    $('sidebar').classList.remove('mobile-open');
    $('mobile-backdrop').classList.remove('open');
}

/* ─── Clipboard ───────────────────────────────────────────────────────────── */
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        toast(`Copied: ${String(text).slice(0, 30)}${String(text).length > 30 ? '…' : ''}`, 'info', 2000);
    }).catch(() => toast('Copy failed', 'error', 2000));
}

/* ─── Account selector handler ───────────────────────────────────────────── */
function onAccountChange(e) {
    const id = e.target.value;
    if (id) selectAccount(parseInt(id));
}

/* ─── Keyboard Shortcuts ─────────────────────────────────────────────────── */
function setupKeyboard() {
    document.addEventListener('keydown', (e) => {
        // Cmd+K / Ctrl+K — command palette
        if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
            e.preventDefault();
            if (state.token) {
                $('command-palette').classList.contains('open') ? closeCommandPalette() : openCommandPalette();
            }
        }
        // Escape — close palette / modals
        if (e.key === 'Escape') {
            closeCommandPalette();
        }
        // T shortcut — go to transfer (when not in an input)
        if (e.key === 't' && !e.target.closest('input,textarea,select') && state.token) {
            navigate('transfer');
        }
    });
}

/* ─── Sidebar Toggle ─────────────────────────────────────────────────────── */
function setupSidebar() {
    const btn = $('sidebar-toggle');
    if (btn) {
        btn.addEventListener('click', () => {
            $('sidebar').classList.toggle('collapsed');
        });
    }
}

/* ─── Login Form ─────────────────────────────────────────────────────────── */
function setupLoginForm() {
    const form = $('login-form');
    if (form) form.addEventListener('submit', (e) => { e.preventDefault(); login(); });
}

/* ─── Public API (window.SP) ─────────────────────────────────────────────── */
window.SP = {
    navigate,
    logout,
    refreshLedger,
    loadNewTransactions,
    filterTransactions,
    txPrevPage,
    txNextPage,
    openCommandPalette,
    closeCommandPalette,
    palettSearch,
    openMobileMenu,
    closeMobileMenu,
    transferReview,
    transferBack,
    transferSubmit,
    transferReset,
    regenIdempotencyKey,
    generateIdempotencyKey,
    copyToClipboard,
    _paletteAction(idx, query) {
        const q = query.toLowerCase();
        const items = q ? PALETTE_ITEMS.filter(i => i.label.toLowerCase().includes(q)) : PALETTE_ITEMS;
        if (items[idx]) { items[idx].action(); closeCommandPalette(); }
    },
};

/* ─── Boot ────────────────────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
    setupLoginForm();
    setupKeyboard();
    setupSidebar();

    const accountSel = $('account-selector');
    if (accountSel) accountSel.addEventListener('change', onAccountChange);

    // Immediately poll health even before login
    pollHealth();
});
