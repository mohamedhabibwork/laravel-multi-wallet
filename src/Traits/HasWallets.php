<?php

namespace HWallet\LaravelMultiWallet\Traits;

use HWallet\LaravelMultiWallet\Exceptions\WalletNotFoundException;
use HWallet\LaravelMultiWallet\Models\Transaction;
use HWallet\LaravelMultiWallet\Models\Transfer;
use HWallet\LaravelMultiWallet\Models\Wallet;
use HWallet\LaravelMultiWallet\Services\WalletManager;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasWallets
{
    /**
     * Get all wallets for this model
     */
    public function wallets(): MorphMany
    {
        return $this->morphMany(Wallet::class, 'holder');
    }

    /**
     * Get transfers where this model is the sender
     */
    public function sentTransfers(): MorphMany
    {
        return $this->morphMany(Transfer::class, 'from');
    }

    /**
     * Get transfers where this model is the receiver
     */
    public function receivedTransfers(): MorphMany
    {
        return $this->morphMany(Transfer::class, 'to');
    }

    /**
     * Alias for sentTransfers (for compatibility)
     */
    public function getTransfersFromAttribute()
    {
        return $this->sentTransfers;
    }

    /**
     * Alias for receivedTransfers (for compatibility)
     */
    public function getTransfersToAttribute()
    {
        return $this->receivedTransfers;
    }

    /**
     * Create a new wallet for this model
     */
    public function createWallet(string $currency, ?string $name = null, array $attributes = []): Wallet
    {
        return app(WalletManager::class)->create($this, $currency, $name, $attributes);
    }

    /**
     * Get or create a wallet for this model
     */
    public function getOrCreateWallet(string $currency, ?string $name = null, array $attributes = []): Wallet
    {
        return $this->getWallet($currency, $name) ?? $this->createWallet($currency, $name, $attributes);
    }

    /**
     * Get a specific wallet by currency and optionally by name
     */
    public function getWallet(string $currency, ?string $name = null): ?Wallet
    {
        $query = $this->wallets()->where('currency', $currency);

        if ($name !== null) {
            $query->where('name', $name);
        }

        return $query->first();
    }

    /**
     * Get the default wallet for a currency
     */
    public function getDefaultWallet(string $currency): ?Wallet
    {
        return $this->wallets()
            ->where('currency', $currency)
            ->whereNull('name')
            ->first();
    }

    /**
     * Get all wallets for a specific currency
     */
    public function getWalletsByCurrency(string $currency)
    {
        return $this->wallets()->where('currency', $currency)->get();
    }

    /**
     * Check if this model has a wallet for the specified currency
     */
    public function hasWallet(string $currency, ?string $name = null): bool
    {
        return $this->getWallet($currency, $name) !== null;
    }

    /**
     * Get balance for a specific currency and balance type
     */
    public function getBalance(string $currency, string $balanceType = 'available'): float
    {
        $wallet = $this->getWallet($currency) ?? $this->getDefaultWallet($currency);

        if (! $wallet) {
            return 0.0;
        }

        return $wallet->getBalance($balanceType);
    }

    /**
     * Credit a wallet for a specific currency
     */
    public function creditWallet(string $currency, float $amount, string $balanceType = 'available', array $meta = []): Transaction
    {
        $wallet = $this->getWallet($currency) ?? $this->getDefaultWallet($currency);

        if (! $wallet) {
            $wallet = $this->createWallet($currency);
        }

        return $wallet->credit($amount, $balanceType, $meta);
    }

    /**
     * Debit a wallet for a specific currency
     */
    public function debitWallet(string $currency, float $amount, string $balanceType = 'available', array $meta = []): Transaction
    {
        $wallet = $this->getWallet($currency) ?? $this->getDefaultWallet($currency);

        if (! $wallet) {
            throw new WalletNotFoundException("No wallet found for currency: {$currency}");
        }

        return $wallet->debit($amount, $balanceType, $meta);
    }

    /**
     * Transfer funds to another entity
     */
    public function transfer(float $amount, string $fromCurrency, $to, ?string $toCurrency = null, array $options = []): Transfer
    {
        $toCurrency = $toCurrency ?? $fromCurrency;

        $fromWallet = $this->getWallet($fromCurrency) ?? $this->getDefaultWallet($fromCurrency);
        $toWallet = $to->getWallet($toCurrency) ?? $to->getDefaultWallet($toCurrency);

        if (! $fromWallet) {
            throw new \InvalidArgumentException("No wallet found for currency: {$fromCurrency}");
        }

        if (! $toWallet) {
            $toWallet = $to->createWallet($toCurrency);
        }

        return app(WalletManager::class)->transfer(
            $fromWallet,
            $toWallet,
            $amount,
            $options
        );
    }

    /**
     * Get total balance across all wallets
     */
    public function getTotalBalance(?string $currency = null): float
    {
        $query = $this->wallets();

        if ($currency) {
            $query->where('currency', $currency);
        }

        return $query->get()->sum(function (Wallet $wallet) {
            return $wallet->getTotalBalance();
        });
    }

    /**
     * Get available balance across all wallets
     */
    public function getAvailableBalance(?string $currency = null): float
    {
        $query = $this->wallets();

        if ($currency) {
            $query->where('currency', $currency);
        }

        return $query->sum('balance_available');
    }

    /**
     * Transfer funds to another entity
     */
    public function transferTo($to, float $amount, string $currency, array $options = []): Transfer
    {
        $fromWallet = $this->getWallet($currency) ?? $this->getDefaultWallet($currency);
        $toWallet = $to->getWallet($currency) ?? $to->getDefaultWallet($currency);

        if (! $fromWallet) {
            throw new \InvalidArgumentException("No wallet found for currency: {$currency}");
        }

        if (! $toWallet) {
            $toWallet = $to->createWallet($currency);
        }

        return app(WalletManager::class)->transfer(
            $fromWallet,
            $toWallet,
            $amount,
            $options
        );
    }

    /**
     * Receive a transfer from another entity
     */
    public function receiveTransferFrom($from, float $amount, string $currency, array $options = []): Transfer
    {
        return $from->transferTo($this, $amount, $currency, $options);
    }

    /**
     * Get all transfers involving this model
     */
    public function getAllTransfers()
    {
        return Transfer::involving($this)->get();
    }

    /**
     * Check if the model can afford a certain amount in a specific currency
     */
    public function canAfford(float $amount, string $currency, string $balanceType = 'available'): bool
    {
        $wallet = $this->getWallet($currency) ?? $this->getDefaultWallet($currency);

        if (! $wallet) {
            return false;
        }

        return $wallet->canDebit($amount, $balanceType);
    }

    /**
     * Force create a wallet (even if one exists)
     */
    public function forceCreateWallet(string $currency, ?string $name = null, array $attributes = []): Wallet
    {
        return $this->createWallet($currency, $name, $attributes);
    }

    /**
     * Delete a wallet and all its transactions
     */
    public function deleteWallet(string $currency, ?string $name = null): bool
    {
        $wallet = $this->getWallet($currency, $name);

        if (! $wallet) {
            return false;
        }

        // Check if wallet has balance
        if ($wallet->getTotalBalance() > 0) {
            throw new \Exception('Cannot delete wallet with remaining balance');
        }

        return $wallet->delete();
    }

    /**
     * Get wallet by slug
     */
    public function getWalletBySlug(string $slug): ?Wallet
    {
        return $this->wallets()->where('slug', $slug)->first();
    }

    /**
     * Get wallet or create if not exists
     */
    public function getWalletOrCreate(string $currency, ?string $name = null, array $attributes = []): Wallet
    {
        return $this->getOrCreateWallet($currency, $name, $attributes);
    }
}
