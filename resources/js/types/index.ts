export interface HealthResponse {
    status: string;
    service: string;
    timestamp: string;
}

export interface User {
    id: string;
    name: string;
    email: string;
}

export interface Account {
    id: string;
    user_id: string;
    balance: string;
    currency: string;
    is_active: boolean;
    created_at?: string;
    updated_at?: string;
}

export type TransactionStatus = 'completed' | 'processing' | 'pending' | 'failed' | 'conflict' | 'reversed';

export interface Transaction {
    id: string;
    idempotency_key: string;
    sender_id: string;
    receiver_id: string;
    amount: string;
    currency: string;
    status: TransactionStatus;
    failure_reason?: string | null;
    created_at: string;
    type?: 'debit' | 'credit';
    risk_level?: 'low' | 'medium' | 'high';
}

export interface ApiResponse<T> {
    status: 'success' | 'error';
    message?: string;
    data?: T;
    error?: string;
}

export interface PaginatedData<T> {
    current_page: number;
    data: T[];
    first_page_url: string;
    from: number | null;
    last_page: number;
    last_page_url: string;
    next_page_url: string | null;
    path: string;
    per_page: number;
    prev_page_url: string | null;
    to: number | null;
    total: number;
}

export interface TransferPayload {
    sender_account_id: string;
    receiver_account_id: string;
    amount: string;
    currency: string;
    idempotency_key: string;
}

export type TransferStep = 'DRAFT' | 'REVIEW' | 'SUBMITTING' | 'PROCESSING' | 'COMPLETED' | 'CONFLICT' | 'FAILED';

export interface SecurityAlert {
    id: string;
    severity: 'info' | 'warning' | 'critical';
    title: string;
    message: string;
    timestamp: string;
    code?: string;
}
