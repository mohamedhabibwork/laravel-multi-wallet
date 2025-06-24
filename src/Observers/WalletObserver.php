<?php

namespace HWallet\LaravelMultiWallet\Observers;

use HWallet\LaravelMultiWallet\Events\WalletCreated;
use HWallet\LaravelMultiWallet\Models\Wallet;
use Illuminate\Support\Facades\Log;

class WalletObserver
{
    /**
     * Handle the Wallet "created" event.
     */
    public function created(Wallet $wallet): void
    {
        // Fire wallet created event
        event(new WalletCreated($wallet));

        // Log wallet creation if audit logging is enabled
        if (config('multi-wallet.audit_logging_enabled', true)) {
            Log::info('Wallet created', [
                'wallet_id' => $wallet->id,
                'holder_type' => $wallet->holder_type,
                'holder_id' => $wallet->holder_id,
                'currency' => $wallet->currency,
                'name' => $wallet->name,
                'slug' => $wallet->slug,
            ]);
        }
    }

    /**
     * Handle the Wallet "updated" event.
     */
    public function updated(Wallet $wallet): void
    {
        if (config('multi-wallet.audit_logging_enabled', true)) {
            $changes = $wallet->getChanges();

            if (! empty($changes)) {
                Log::info('Wallet updated', [
                    'wallet_id' => $wallet->id,
                    'changes' => $changes,
                    'original' => $wallet->getOriginal(),
                ]);
            }
        }
    }

    /**
     * Handle the Wallet "deleted" event.
     */
    public function deleted(Wallet $wallet): void
    {
        if (config('multi-wallet.audit_logging_enabled', true)) {
            Log::info('Wallet deleted', [
                'wallet_id' => $wallet->id,
                'holder_type' => $wallet->holder_type,
                'holder_id' => $wallet->holder_id,
                'currency' => $wallet->currency,
                'final_balance' => $wallet->getTotalBalance(),
            ]);
        }
    }

    /**
     * Handle the Wallet "saving" event.
     */
    public function saving(Wallet $wallet): void
    {
        // Validate balance limits if configured
        $maxBalance = config('multi-wallet.wallet_limits.max_balance');
        if ($maxBalance && $wallet->getTotalBalance() > $maxBalance) {
            throw new \Exception("Wallet balance exceeds maximum limit of {$maxBalance}");
        }

        $minBalance = config('multi-wallet.wallet_limits.min_balance', 0);
        if ($wallet->getTotalBalance() < $minBalance) {
            throw new \Exception("Wallet balance below minimum limit of {$minBalance}");
        }
    }
}
