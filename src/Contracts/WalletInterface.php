<?php

namespace HWallet\LaravelMultiWallet\Contracts;

use HWallet\LaravelMultiWallet\Enums\BalanceType;
use HWallet\LaravelMultiWallet\Models\Transaction;

interface WalletInterface
{
    /**
     * Get the wallet balance for a specific balance type
     */
    public function getBalance(BalanceType|string $balanceType = 'available'): float;

    /**
     * Get the total balance across all balance types
     */
    public function getTotalBalance(): float;

    /**
     * Credit the wallet with the specified amount
     */
    public function credit(float $amount, BalanceType|string $balanceType = 'available', array $meta = []): Transaction;

    /**
     * Debit the wallet with the specified amount
     */
    public function debit(float $amount, BalanceType|string $balanceType = 'available', array $meta = []): Transaction;

    /**
     * Check if the wallet can be debited with the specified amount
     */
    public function canDebit(float $amount, BalanceType|string $balanceType = 'available'): bool;

    /**
     * Move amount to pending balance
     */
    public function moveToPending(float $amount, string $description = ''): Transaction;

    /**
     * Confirm pending amount and move to available
     */
    public function confirmPending(float $amount, string $description = ''): bool;

    /**
     * Cancel pending amount
     */
    public function cancelPending(float $amount, string $description = ''): bool;

    /**
     * Freeze funds in the wallet
     */
    public function freeze(float $amount, string $description = ''): Transaction;

    /**
     * Unfreeze funds in the wallet
     */
    public function unfreeze(float $amount, string $description = ''): Transaction;

    /**
     * Add trial balance
     */
    public function addTrialBalance(float $amount, string $description = ''): Transaction;

    /**
     * Convert trial balance to available balance
     */
    public function convertTrialToAvailable(float $amount, string $description = ''): bool;
}
