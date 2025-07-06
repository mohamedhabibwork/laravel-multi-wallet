<?php

namespace HWallet\LaravelMultiWallet\Contracts;

use HWallet\LaravelMultiWallet\Models\Wallet;

interface BulkWalletOperationInterface
{
    /**
     * Execute bulk credit operations
     */
    public function bulkCredit(array $operations): array;

    /**
     * Execute bulk debit operations
     */
    public function bulkDebit(array $operations): array;

    /**
     * Execute bulk transfer operations
     */
    public function bulkTransfer(array $operations): array;

    /**
     * Execute bulk freeze operations
     */
    public function bulkFreeze(array $operations): array;

    /**
     * Execute bulk unfreeze operations
     */
    public function bulkUnfreeze(array $operations): array;

    /**
     * Execute bulk wallet creation
     */
    public function bulkCreateWallets(array $walletData): array;

    /**
     * Execute bulk balance updates
     */
    public function bulkUpdateBalances(array $operations): array;

    /**
     * Execute bulk transactions with validation
     */
    public function bulkTransactionsWithValidation(array $operations): array;

    /**
     * Execute bulk pending operations
     */
    public function bulkPendingOperations(array $operations): array;

    /**
     * Execute bulk confirmation operations
     */
    public function bulkConfirmOperations(array $operations): array;
}
