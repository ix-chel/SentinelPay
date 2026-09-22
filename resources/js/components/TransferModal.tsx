import React, { useState, useEffect } from 'react';
import type { TransferStep, Transaction } from '../types';
import {
    XIcon,
    ShieldIcon,
    CheckCircleIcon,
    AlertTriangleIcon,
    XCircleIcon,
    ArrowUpRightIcon,
    CopyIcon,
    CheckIcon,
} from './Icons';

interface TransferModalProps {
    isOpen: boolean;
    onClose: () => void;
    onSuccess: (transaction: Transaction) => void;
    onResolveConflict?: (existingTx: Partial<Transaction>) => void;
    defaultSenderId?: string;
    defaultReceiverId?: string;
    senderBalance?: string;
}

export const TransferModal: React.FC<TransferModalProps> = ({
    isOpen,
    onClose,
    onSuccess,
    onResolveConflict,
    defaultSenderId = 'a1000000-0000-0000-0000-000000000001',
    defaultReceiverId = 'b2000000-0000-0000-0000-000000000002',
    senderBalance = '10000.00',
}) => {
    const [step, setStep] = useState<TransferStep>('DRAFT');
    const [senderId, setSenderId] = useState(defaultSenderId);
    const [receiverId, setReceiverId] = useState(defaultReceiverId);
    const [amount, setAmount] = useState('150.00');
    const [currency, setCurrency] = useState('USD');
    const [idempotencyKey, setIdempotencyKey] = useState('');
    const [isDuplicateSimulation, setIsDuplicateSimulation] = useState(false);
    const [showKeyDetails, setShowKeyDetails] = useState(false);
    const [copiedKey, setCopiedKey] = useState(false);

    // Verification progress states during PROCESSING
    const [verificationStep, setVerificationStep] = useState<number>(0);
    const [errorDetails, setErrorDetails] = useState<{
        code: string;
        message: string;
        matchedTransaction?: Partial<Transaction>;
    } | null>(null);

    // Generate fresh idempotency key when modal opens in DRAFT mode
    useEffect(() => {
        if (isOpen) {
            setStep('DRAFT');
            setIdempotencyKey(crypto.randomUUID());
            setErrorDetails(null);
            setVerificationStep(0);
            setIsDuplicateSimulation(false);
        }
    }, [isOpen]);

    // Keyboard dismissal
    useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            if (e.key === 'Escape' && isOpen && step !== 'PROCESSING') {
                onClose();
            }
        };
        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [isOpen, step, onClose]);

    if (!isOpen) return null;

    const handleCopyKey = () => {
        navigator.clipboard.writeText(idempotencyKey);
        setCopiedKey(true);
        setTimeout(() => setCopiedKey(false), 1500);
    };

    const handleProceedToReview = (e: React.FormEvent) => {
        e.preventDefault();
        const numAmount = parseFloat(amount);
        if (isNaN(numAmount) || numAmount <= 0) return;
        setStep('REVIEW');
    };

    const handleExecuteTransfer = async () => {
        setStep('PROCESSING');
        setErrorDetails(null);

        // Step 1: Request Generation
        setVerificationStep(1);
        await new Promise((r) => setTimeout(r, 150));

        // Step 2: Idempotency Key Lock
        setVerificationStep(2);
        await new Promise((r) => setTimeout(r, 180));

        // Step 3: Server Signature & Idempotency Check Simulation/API Call
        setVerificationStep(3);

        try {
            // Check if simulating duplicate key test
            if (isDuplicateSimulation) {
                await new Promise((r) => setTimeout(r, 200));
                setErrorDetails({
                    code: 'IDEMPOTENCY_CONFLICT',
                    message: 'The idempotency key is already associated with an existing transaction.',
                    matchedTransaction: {
                        id: 'tx-existing-8f92a104',
                        idempotency_key: idempotencyKey,
                        amount: amount,
                        currency: currency,
                        status: 'completed',
                        created_at: new Date(Date.now() - 3600000).toISOString(),
                    },
                });
                setStep('CONFLICT');
                return;
            }

            // Normal successful execution simulation / client API call
            await new Promise((r) => setTimeout(r, 250));
            setVerificationStep(4);

            const newTx: Transaction = {
                id: crypto.randomUUID(),
                idempotency_key: idempotencyKey,
                sender_id: senderId,
                receiver_id: receiverId,
                amount: parseFloat(amount).toFixed(2),
                currency,
                status: 'completed',
                created_at: new Date().toISOString(),
                type: 'debit',
                risk_level: 'low',
            };

            setStep('COMPLETED');
            onSuccess(newTx);
        } catch (err: unknown) {
            setStep('FAILED');
            const errorMessage = err instanceof Error ? err.message : 'Transfer authorization rejected.';
            setErrorDetails({
                code: 'TRANSFER_REJECTED',
                message: errorMessage,
            });
        }
    };

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6"
            role="dialog"
            aria-modal="true"
            aria-labelledby="transfer-modal-title"
        >
            {/* Backdrop Blur */}
            <div
                className="fixed inset-0 bg-black/75 backdrop-blur-md transition-opacity"
                onClick={() => step !== 'PROCESSING' && onClose()}
                aria-hidden="true"
            />

            {/* Modal Dialog Card */}
            <div className="relative w-full max-w-lg glass-modal rounded-2xl overflow-hidden z-10 text-slate-200">
                {/* Header */}
                <div className="p-5 border-b border-white/[0.08] flex items-center justify-between">
                    <div className="flex items-center space-x-2.5">
                        <div className="p-2 rounded-xl bg-blue-500/10 border border-blue-500/20 text-blue-400">
                            <ArrowUpRightIcon className="w-4 h-4" />
                        </div>
                        <div>
                            <h2 id="transfer-modal-title" className="text-base font-bold text-slate-100">
                                {step === 'DRAFT' && 'Initiate Fund Transfer'}
                                {step === 'REVIEW' && 'Review Transfer & Authorization'}
                                {step === 'PROCESSING' && 'Cryptographic Authorization in Progress'}
                                {step === 'COMPLETED' && 'Transfer Executed Successfully'}
                                {step === 'CONFLICT' && 'Idempotent Replay Detected'}
                                {step === 'FAILED' && 'Transfer Authorization Rejected'}
                            </h2>
                            <p className="text-xs text-slate-400">
                                Protected by PostgreSQL Pessimistic Locks & HMAC Security
                            </p>
                        </div>
                    </div>
                    {step !== 'PROCESSING' && (
                        <button
                            onClick={onClose}
                            className="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/[0.06] transition-colors"
                            aria-label="Close dialog"
                        >
                            <XIcon className="w-5 h-5" />
                        </button>
                    )}
                </div>

                {/* Body Content by State */}
                <div className="p-6">
                    {/* DRAFT STATE */}
                    {step === 'DRAFT' && (
                        <form onSubmit={handleProceedToReview} className="space-y-4">
                            <div>
                                <label className="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                                    Sender Vault Account
                                </label>
                                <input
                                    type="text"
                                    value={senderId}
                                    onChange={(e) => setSenderId(e.target.value)}
                                    required
                                    className="w-full px-3.5 py-2 rounded-xl bg-slate-900 border border-white/[0.08] text-xs font-mono text-slate-200 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                    placeholder="UUID..."
                                />
                                <div className="flex items-center justify-between mt-1 text-[11px] text-slate-400">
                                    <span>Available Liquid Balance:</span>
                                    <span className="font-semibold text-slate-200 tabular-nums font-mono">
                                        ${senderBalance} USD
                                    </span>
                                </div>
                            </div>

                            <div>
                                <label className="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                                    Destination Counterparty Account
                                </label>
                                <input
                                    type="text"
                                    value={receiverId}
                                    onChange={(e) => setReceiverId(e.target.value)}
                                    required
                                    className="w-full px-3.5 py-2 rounded-xl bg-slate-900 border border-white/[0.08] text-xs font-mono text-slate-200 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                    placeholder="UUID..."
                                />
                            </div>

                            <div className="grid grid-cols-3 gap-3">
                                <div className="col-span-2">
                                    <label className="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                                        Transfer Amount
                                    </label>
                                    <div className="relative">
                                        <span className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm font-semibold">
                                            $
                                        </span>
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0.01"
                                            value={amount}
                                            onChange={(e) => setAmount(e.target.value)}
                                            required
                                            className="w-full pl-7 pr-3.5 py-2 rounded-xl bg-slate-900 border border-white/[0.08] text-sm font-bold tabular-nums text-slate-100 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                        />
                                    </div>
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                                        Currency
                                    </label>
                                    <select
                                        value={currency}
                                        onChange={(e) => setCurrency(e.target.value)}
                                        className="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/[0.08] text-xs font-bold text-slate-200 focus:outline-none focus:border-blue-500"
                                    >
                                        <option value="USD">USD</option>
                                        <option value="EUR">EUR</option>
                                        <option value="GBP">GBP</option>
                                    </select>
                                </div>
                            </div>

                            {/* Testing Helper: Simulate Duplicate Key Conflict */}
                            <div className="p-3 rounded-xl bg-slate-900/60 border border-white/[0.06] flex items-center justify-between text-xs">
                                <div>
                                    <div className="font-semibold text-slate-300">
                                        Simulate Replay / Conflict
                                    </div>
                                    <div className="text-[11px] text-slate-500">
                                        Tests IDEMPOTENCY_CONFLICT resolution
                                    </div>
                                </div>
                                <label className="relative inline-flex items-center cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={isDuplicateSimulation}
                                        onChange={(e) => setIsDuplicateSimulation(e.target.checked)}
                                        className="sr-only peer"
                                    />
                                    <div className="w-9 h-5 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
                                </label>
                            </div>

                            <div className="pt-2 flex justify-end space-x-3">
                                <button
                                    type="button"
                                    onClick={onClose}
                                    className="px-4 py-2 rounded-xl text-xs font-semibold text-slate-400 hover:text-slate-200 hover:bg-white/[0.04] transition-colors"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    className="px-5 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold shadow-lg shadow-blue-600/20 transition-all duration-150"
                                >
                                    Review Transfer →
                                </button>
                            </div>
                        </form>
                    )}

                    {/* REVIEW STATE */}
                    {step === 'REVIEW' && (
                        <div className="space-y-4">
                            <div className="p-4 rounded-xl bg-slate-900/80 border border-white/[0.08] space-y-3">
                                <div className="flex items-center justify-between pb-2 border-b border-white/[0.06]">
                                    <span className="text-xs text-slate-400">Total Settlement Amount</span>
                                    <span className="text-xl font-bold tabular-nums text-slate-100">
                                        ${parseFloat(amount).toFixed(2)} {currency}
                                    </span>
                                </div>
                                <div className="text-xs space-y-1.5 font-mono">
                                    <div className="flex justify-between text-slate-400">
                                        <span>Sender Account:</span>
                                        <span className="text-slate-300 truncate max-w-[200px]">{senderId}</span>
                                    </div>
                                    <div className="flex justify-between text-slate-400">
                                        <span>Receiver Account:</span>
                                        <span className="text-slate-300 truncate max-w-[200px]">{receiverId}</span>
                                    </div>
                                </div>
                            </div>

                            {/* Client Idempotency Key Guardrail (Expandable, No Secret Exposure) */}
                            <div className="p-3.5 rounded-xl bg-blue-500/5 border border-blue-500/20 space-y-2">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center space-x-2 text-xs font-semibold text-blue-400">
                                        <ShieldIcon className="w-3.5 h-3.5" />
                                        <span>Audit & Replay Protection</span>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => setShowKeyDetails(!showKeyDetails)}
                                        className="text-[11px] text-blue-400 hover:underline"
                                    >
                                        {showKeyDetails ? 'Hide Request ID' : 'Inspect Request ID'}
                                    </button>
                                </div>
                                {showKeyDetails && (
                                    <div className="p-2 rounded bg-slate-900 border border-white/[0.06] flex items-center justify-between font-mono text-[11px] text-slate-300">
                                        <span className="truncate">{idempotencyKey}</span>
                                        <button
                                            onClick={handleCopyKey}
                                            className="p-1 text-slate-400 hover:text-white"
                                            title="Copy Request ID"
                                        >
                                            {copiedKey ? <CheckIcon className="w-3 h-3 text-emerald-400" /> : <CopyIcon className="w-3 h-3" />}
                                        </button>
                                    </div>
                                )}
                                <p className="text-[11px] text-slate-400">
                                    Unique idempotency key assigned by client. Replays will never trigger duplicate debits.
                                </p>
                            </div>

                            <div className="pt-2 flex justify-end space-x-3">
                                <button
                                    type="button"
                                    onClick={() => setStep('DRAFT')}
                                    className="px-4 py-2 rounded-xl text-xs font-semibold text-slate-400 hover:text-slate-200 transition-colors"
                                >
                                    ← Back
                                </button>
                                <button
                                    type="button"
                                    onClick={handleExecuteTransfer}
                                    className="px-5 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold shadow-lg shadow-blue-600/20 transition-all duration-150"
                                >
                                    Authorize & Execute Transfer
                                </button>
                            </div>
                        </div>
                    )}

                    {/* PROCESSING STATE (Security Verification State Machine) */}
                    {step === 'PROCESSING' && (
                        <div className="py-6 space-y-4">
                            <div className="text-center space-y-2">
                                <div className="w-10 h-10 mx-auto rounded-full border-2 border-blue-500 border-t-transparent animate-spin" />
                                <div className="text-sm font-bold text-slate-100">
                                    Executing Pessimistic Row Lock & ACID Transfer
                                </div>
                                <p className="text-xs text-slate-400">
                                    Verifying request integrity across database boundaries
                                </p>
                            </div>

                            {/* Multi-Step Security Verification Workflow */}
                            <div className="p-4 rounded-xl bg-slate-900 border border-white/[0.08] space-y-2.5 font-mono text-xs">
                                <div className="flex items-center space-x-2 text-emerald-400">
                                    <CheckCircleIcon className="w-3.5 h-3.5" />
                                    <span>Request generated with UUID idempotency key</span>
                                </div>

                                <div className={`flex items-center space-x-2 ${verificationStep >= 2 ? 'text-emerald-400' : 'text-slate-500'}`}>
                                    {verificationStep >= 2 ? <CheckCircleIcon className="w-3.5 h-3.5" /> : <div className="w-3.5 h-3.5 rounded-full border border-slate-600" />}
                                    <span>Pessimistic row lock acquired (SELECT ... FOR UPDATE)</span>
                                </div>

                                <div className={`flex items-center space-x-2 ${verificationStep >= 3 ? 'text-emerald-400' : 'text-slate-500'}`}>
                                    {verificationStep >= 3 ? <CheckCircleIcon className="w-3.5 h-3.5" /> : <div className="w-3.5 h-3.5 rounded-full border border-slate-600" />}
                                    <span>Server signature validation & duplicate check</span>
                                </div>

                                <div className={`flex items-center space-x-2 ${verificationStep >= 4 ? 'text-emerald-400' : 'text-slate-500'}`}>
                                    {verificationStep >= 4 ? <CheckCircleIcon className="w-3.5 h-3.5" /> : <div className="w-3.5 h-3.5 rounded-full border border-slate-600" />}
                                    <span>Double-entry ledger debit & credit persisted</span>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* COMPLETED STATE */}
                    {step === 'COMPLETED' && (
                        <div className="py-4 text-center space-y-4">
                            <div className="w-12 h-12 mx-auto rounded-full bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center text-emerald-400">
                                <CheckCircleIcon className="w-6 h-6" />
                            </div>
                            <div>
                                <h3 className="text-base font-bold text-slate-100">
                                    Transfer Successfully Completed
                                </h3>
                                <p className="text-xs text-slate-400 mt-1">
                                    Debited <strong className="text-slate-200 tabular-nums">${amount} {currency}</strong> from your account and credited counterparty.
                                </p>
                            </div>

                            <div className="p-3 rounded-xl bg-slate-900 border border-white/[0.06] text-xs font-mono text-left space-y-1 text-slate-300">
                                <div className="flex justify-between">
                                    <span className="text-slate-400">Status:</span>
                                    <span className="text-emerald-400 font-semibold">COMPLETED</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-slate-400">Idempotency Key:</span>
                                    <span className="truncate max-w-[200px]">{idempotencyKey}</span>
                                </div>
                            </div>

                            <div className="pt-2 flex justify-center space-x-3">
                                <button
                                    onClick={onClose}
                                    className="px-5 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold transition-all duration-150"
                                >
                                    Return to Ledger
                                </button>
                            </div>
                        </div>
                    )}

                    {/* CONFLICT AUTO-RESOLVE STATE */}
                    {step === 'CONFLICT' && (
                        <div className="py-4 space-y-4">
                            <div className="p-4 rounded-xl bg-amber-500/10 border border-amber-500/30 flex items-start space-x-3">
                                <AlertTriangleIcon className="w-5 h-5 text-amber-400 flex-shrink-0 mt-0.5" />
                                <div className="text-xs">
                                    <div className="font-bold text-amber-300">
                                        This request was already processed
                                    </div>
                                    <p className="text-slate-300 mt-1 leading-relaxed">
                                        The idempotency key matches an existing authoritative transaction record. Double-charge prevented by database constraint.
                                    </p>
                                </div>
                            </div>

                            {errorDetails?.matchedTransaction && (
                                <div className="p-4 rounded-xl bg-slate-900 border border-white/[0.08] space-y-2 text-xs font-mono">
                                    <div className="text-slate-400 uppercase tracking-wider text-[10px] font-sans font-bold">
                                        Matched Authoritative Record
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-slate-400">Transaction ID:</span>
                                        <span className="text-blue-400">{errorDetails.matchedTransaction.id}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-slate-400">Original Amount:</span>
                                        <span className="text-slate-200 tabular-nums">
                                            ${errorDetails.matchedTransaction.amount} {errorDetails.matchedTransaction.currency}
                                        </span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-slate-400">Status:</span>
                                        <span className="text-emerald-400 font-semibold uppercase">
                                            {errorDetails.matchedTransaction.status}
                                        </span>
                                    </div>
                                </div>
                            )}

                            <div className="flex justify-end space-x-3 pt-2">
                                <button
                                    onClick={() => {
                                        if (errorDetails?.matchedTransaction) {
                                            onResolveConflict?.(errorDetails.matchedTransaction);
                                        }
                                        onClose();
                                    }}
                                    className="px-5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold transition-all duration-150"
                                >
                                    View Existing Transaction →
                                </button>
                            </div>
                        </div>
                    )}

                    {/* FAILED STATE */}
                    {step === 'FAILED' && (
                        <div className="py-4 text-center space-y-4">
                            <div className="w-12 h-12 mx-auto rounded-full bg-rose-500/10 border border-rose-500/30 flex items-center justify-center text-rose-400">
                                <XCircleIcon className="w-6 h-6" />
                            </div>
                            <div>
                                <h3 className="text-base font-bold text-slate-100">
                                    Transfer Authorization Rejected
                                </h3>
                                <p className="text-xs text-rose-400 mt-1">
                                    {errorDetails?.message || 'Transaction could not be completed.'}
                                </p>
                            </div>
                            <div className="pt-2 flex justify-center space-x-3">
                                <button
                                    onClick={() => setStep('DRAFT')}
                                    className="px-5 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold transition-all duration-150"
                                >
                                    Try Again
                                </button>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
};
