<?php

namespace HWallet\LaravelMultiWallet\Contracts;

use HWallet\LaravelMultiWallet\Models\Transaction;
use HWallet\LaravelMultiWallet\Models\Transfer;
use HWallet\LaravelMultiWallet\Models\Wallet;
use Illuminate\Database\Eloquent\Model;

/**
 * Interface for wallet operation validation
 */
interface ValidatorInterface
{
    /**
     * Validate wallet creation
     */
    public function validateWalletCreation(Model $holder, string $currency, ?string $name = null, array $attributes = []): array;

    /**
     * Validate wallet update
     */
    public function validateWalletUpdate(Wallet $wallet, array $attributes): array;

    /**
     * Validate transaction creation
     */
    public function validateTransactionCreation(Wallet $wallet, float $amount, string $balanceType, array $meta = []): array;

    /**
     * Validate transfer creation
     */
    public function validateTransferCreation(Wallet $fromWallet, Wallet $toWallet, float $amount, array $options = []): array;

    /**
     * Validate bulk operations
     */
    public function validateBulkOperations(array $operations, string $operationType): array;

    /**
     * Validate wallet configuration
     */
    public function validateWalletConfiguration(array $config): array;

    /**
     * Validate currency code
     */
    public function validateCurrency(string $currency): array;

    /**
     * Validate amount
     */
    public function validateAmount(float $amount, ?float $minAmount = null, ?float $maxAmount = null): array;

    /**
     * Validate balance type
     */
    public function validateBalanceType(string $balanceType): array;

    /**
     * Validate transaction type
     */
    public function validateTransactionType(string $transactionType): array;

    /**
     * Validate transfer status
     */
    public function validateTransferStatus(string $status): array;

    /**
     * Validate metadata
     */
    public function validateMetadata(array $metadata, string $type = 'transaction'): array;

    /**
     * Check if validation passed
     */
    public function isValid(array $validationResult): bool;

    /**
     * Get validation errors
     */
    public function getErrors(array $validationResult): array;

    /**
     * Get validation warnings
     */
    public function getWarnings(array $validationResult): array;
}
