import axios, { type AxiosInstance, type AxiosRequestConfig } from 'axios';
import type { HealthResponse } from '../types';

/**
 * SentinelPay Centralized API Client
 *
 * All frontend HTTP communication routes through Laravel API (/api/v1/*).
 * React NEVER directly connects to the database or Supabase credentials.
 */
const apiClient: AxiosInstance = axios.create({
    baseURL: '/api/v1',
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
    timeout: 10000,
});

// Request interceptor for attaching Sanctum Bearer token when available
apiClient.interceptors.request.use((config) => {
    const token = typeof window !== 'undefined' ? localStorage.getItem('auth_token') : null;
    if (token && config.headers) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
}, (error) => Promise.reject(error));

// Response interceptor for unified error normalization
apiClient.interceptors.response.use(
    (response) => response,
    (error) => {
        // Future global error handling (e.g. 401 session expiry)
        return Promise.reject(error);
    }
);

/**
 * API Service Methods
 */
export const api = {
    /**
     * Check backend service health status
     */
    async getHealth(): Promise<HealthResponse> {
        const response = await apiClient.get<HealthResponse>('/health');
        return response.data;
    },

    /**
     * Query account balance
     */
    async getAccountBalance(accountId: string): Promise<{ account_id: string; balance: string; currency: string; is_active: boolean }> {
        const response = await apiClient.get<{ status: string; data: { account_id: string; balance: string; currency: string; is_active: boolean } }>(`/accounts/${accountId}/balance`);
        return response.data.data;
    },

    /**
     * Query account transactions
     */
    async getAccountTransactions(accountId: string, perPage = 20): Promise<unknown> {
        const response = await apiClient.get<{ status: string; data: unknown }>(`/accounts/${accountId}/transactions?per_page=${perPage}`);
        return response.data.data;
    },

    /**
     * Execute fund transfer
     */
    async createTransfer(payload: Record<string, unknown>, signature?: string): Promise<unknown> {
        const headers: Record<string, string> = {};
        if (signature) {
            headers['X-Signature'] = signature;
        }
        const response = await apiClient.post<{ status: string; data: unknown }>('/transfers', payload, { headers });
        return response.data;
    },

    /**
     * Generic GET helper
     */
    async get<T>(url: string, config?: AxiosRequestConfig): Promise<T> {
        const response = await apiClient.get<T>(url, config);
        return response.data;
    },

    /**
     * Generic POST helper
     */
    async post<T>(url: string, data?: unknown, config?: AxiosRequestConfig): Promise<T> {
        const response = await apiClient.post<T>(url, data, config);
        return response.data;
    },
};

export default apiClient;
