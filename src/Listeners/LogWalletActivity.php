<?php

namespace HWallet\LaravelMultiWallet\Listeners;

use HWallet\LaravelMultiWallet\Events\ExchangeRateUpdated;
use HWallet\LaravelMultiWallet\Events\SuspiciousActivityDetected;
use HWallet\LaravelMultiWallet\Events\TransactionConfirmed;
use HWallet\LaravelMultiWallet\Events\TransactionCreated;
use HWallet\LaravelMultiWallet\Events\TransactionFailed;
use HWallet\LaravelMultiWallet\Events\TransactionReversed;
use HWallet\LaravelMultiWallet\Events\TransferCompleted;
use HWallet\LaravelMultiWallet\Events\TransferFailed;
use HWallet\LaravelMultiWallet\Events\TransferInitiated;
use HWallet\LaravelMultiWallet\Events\TransferPending;
use HWallet\LaravelMultiWallet\Events\TransferRejected;
use HWallet\LaravelMultiWallet\Events\WalletBalanceChanged;
use HWallet\LaravelMultiWallet\Events\WalletCreated;
use HWallet\LaravelMultiWallet\Events\WalletDeleted;
use HWallet\LaravelMultiWallet\Events\WalletFrozen;
use HWallet\LaravelMultiWallet\Events\WalletLimitExceeded;
use HWallet\LaravelMultiWallet\Events\WalletReconciled;
use HWallet\LaravelMultiWallet\Events\WalletUnfrozen;
use HWallet\LaravelMultiWallet\Events\WalletUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class LogWalletActivity implements ShouldQueue
{
    public function handleWalletCreated(WalletCreated $event): void
    {
        Log::info('Wallet created', [
            'wallet_id' => $event->wallet->id,
            'holder_type' => $event->wallet->holder_type,
            'holder_id' => $event->wallet->holder_id,
            'currency' => $event->wallet->currency,
            'name' => $event->wallet->name,
        ]);
    }

    public function handleWalletUpdated(WalletUpdated $event): void
    {
        Log::info('Wallet updated', [
            'wallet_id' => $event->wallet->id,
            'changes' => $event->changes,
            'reason' => $event->reason,
        ]);
    }

    public function handleWalletDeleted(WalletDeleted $event): void
    {
        Log::info('Wallet deleted', [
            'wallet_id' => $event->wallet->id,
            'reason' => $event->reason,
        ]);
    }

    public function handleWalletBalanceChanged(WalletBalanceChanged $event): void
    {
        Log::info('Wallet balance changed', [
            'wallet_id' => $event->wallet->id,
            'balance_type' => $event->balanceType,
            'old_balance' => $event->oldBalance,
            'new_balance' => $event->newBalance,
            'change' => $event->change,
            'reason' => $event->reason,
        ]);
    }

    public function handleWalletFrozen(WalletFrozen $event): void
    {
        Log::info('Wallet frozen', [
            'wallet_id' => $event->wallet->id,
            'amount' => $event->amount,
            'reason' => $event->reason,
            'frozen_by' => $event->frozenBy,
        ]);
    }

    public function handleWalletUnfrozen(WalletUnfrozen $event): void
    {
        Log::info('Wallet unfrozen', [
            'wallet_id' => $event->wallet->id,
            'amount' => $event->amount,
            'reason' => $event->reason,
            'unfrozen_by' => $event->unfrozenBy,
        ]);
    }

    public function handleWalletLimitExceeded(WalletLimitExceeded $event): void
    {
        Log::warning('Wallet limit exceeded', [
            'wallet_id' => $event->wallet->id,
            'limit_type' => $event->limitType,
            'current_value' => $event->currentValue,
            'limit_value' => $event->limitValue,
            'operation' => $event->operation,
        ]);
    }

    public function handleWalletReconciled(WalletReconciled $event): void
    {
        Log::info('Wallet reconciled', [
            'wallet_id' => $event->wallet->id,
            'discrepancies' => $event->discrepancies,
            'corrections' => $event->corrections,
            'reconciled_by' => $event->reconciledBy,
        ]);
    }

    public function handleTransactionCreated(TransactionCreated $event): void
    {
        Log::info('Transaction created', [
            'transaction_id' => $event->transaction->id,
            'wallet_id' => $event->transaction->wallet_id,
            'type' => $event->transaction->type,
            'amount' => $event->transaction->amount,
            'balance_type' => $event->transaction->balance_type,
        ]);
    }

    public function handleTransactionConfirmed(TransactionConfirmed $event): void
    {
        Log::info('Transaction confirmed', [
            'transaction_id' => $event->transaction->id,
            'reason' => $event->reason,
        ]);
    }

    public function handleTransactionFailed(TransactionFailed $event): void
    {
        Log::error('Transaction failed', [
            'transaction_id' => $event->transaction->id,
            'reason' => $event->reason,
            'error_code' => $event->errorCode,
        ]);
    }

    public function handleTransactionReversed(TransactionReversed $event): void
    {
        Log::info('Transaction reversed', [
            'original_transaction_id' => $event->originalTransaction->id,
            'reversal_transaction_id' => $event->reversalTransaction->id,
            'reason' => $event->reason,
        ]);
    }

    public function handleTransferInitiated(TransferInitiated $event): void
    {
        Log::info('Transfer initiated', [
            'transfer_id' => $event->transfer->id,
            'initiated_by' => $event->initiatedBy,
        ]);
    }

    public function handleTransferCompleted(TransferCompleted $event): void
    {
        Log::info('Transfer completed', [
            'transfer_id' => $event->transfer->id,
            'from_type' => $event->transfer->from_type,
            'from_id' => $event->transfer->from_id,
            'to_type' => $event->transfer->to_type,
            'to_id' => $event->transfer->to_id,
            'status' => $event->transfer->status,
        ]);
    }

    public function handleTransferFailed(TransferFailed $event): void
    {
        Log::error('Transfer failed', [
            'transfer_id' => $event->transfer->id,
            'reason' => $event->reason,
            'error_code' => $event->errorCode,
        ]);
    }

    public function handleTransferRejected(TransferRejected $event): void
    {
        Log::warning('Transfer rejected', [
            'transfer_id' => $event->transfer->id,
            'reason' => $event->reason,
            'rejected_by' => $event->rejectedBy,
        ]);
    }

    public function handleTransferPending(TransferPending $event): void
    {
        Log::info('Transfer pending', [
            'transfer_id' => $event->transfer->id,
            'pending_reason' => $event->pendingReason,
        ]);
    }

    public function handleSuspiciousActivityDetected(SuspiciousActivityDetected $event): void
    {
        Log::warning('Suspicious activity detected', [
            'wallet_id' => $event->wallet->id,
            'activity_type' => $event->activityType,
            'risk_score' => $event->riskScore,
            'details' => $event->details,
            'detected_by' => $event->detectedBy,
        ]);
    }

    public function handleExchangeRateUpdated(ExchangeRateUpdated $event): void
    {
        Log::info('Exchange rate updated', [
            'from_currency' => $event->fromCurrency,
            'to_currency' => $event->toCurrency,
            'old_rate' => $event->oldRate,
            'new_rate' => $event->newRate,
            'source' => $event->source,
        ]);
    }
}
