<?php

namespace HWallet\LaravelMultiWallet\Observers;

use HWallet\LaravelMultiWallet\Events\TransactionCreated;
use HWallet\LaravelMultiWallet\Models\Transaction;
use Illuminate\Support\Facades\Log;

class TransactionObserver
{
    /**
     * Handle the Transaction "created" event.
     */
    public function created(Transaction $transaction): void
    {
        // Fire transaction created event
        event(new TransactionCreated($transaction));

        // Log transaction creation if audit logging is enabled
        if (config('multi-wallet.audit_logging_enabled', true)) {
            Log::info('Transaction created', [
                'transaction_id' => $transaction->id,
                'wallet_id' => $transaction->wallet_id,
                'type' => $transaction->type,
                'amount' => $transaction->amount,
                'balance_type' => $transaction->balance_type,
                'confirmed' => $transaction->confirmed,
                'uuid' => $transaction->uuid,
                'payable_type' => $transaction->payable_type,
                'payable_id' => $transaction->payable_id,
            ]);
        }
    }

    /**
     * Handle the Transaction "updated" event.
     */
    public function updated(Transaction $transaction): void
    {
        if (config('multi-wallet.audit_logging_enabled', true)) {
            $changes = $transaction->getChanges();

            if (! empty($changes)) {
                Log::info('Transaction updated', [
                    'transaction_id' => $transaction->id,
                    'changes' => $changes,
                    'original' => $transaction->getOriginal(),
                ]);
            }
        }
    }

    /**
     * Handle the Transaction "creating" event.
     */
    public function creating(Transaction $transaction): void
    {
        // Validate transaction limits
        $maxAmount = config('multi-wallet.transaction_limits.max_amount');
        if ($maxAmount && $transaction->amount > $maxAmount) {
            throw new \Exception("Transaction amount exceeds maximum limit of {$maxAmount}");
        }

        $minAmount = config('multi-wallet.transaction_limits.min_amount', 0.01);
        if ($transaction->amount < $minAmount) {
            throw new \Exception("Transaction amount below minimum limit of {$minAmount}");
        }
    }

    /**
     * Handle the Transaction "deleted" event.
     */
    public function deleted(Transaction $transaction): void
    {
        if (config('multi-wallet.audit_logging_enabled', true)) {
            Log::warning('Transaction deleted', [
                'transaction_id' => $transaction->id,
                'wallet_id' => $transaction->wallet_id,
                'type' => $transaction->type,
                'amount' => $transaction->amount,
                'balance_type' => $transaction->balance_type,
            ]);
        }
    }
}
