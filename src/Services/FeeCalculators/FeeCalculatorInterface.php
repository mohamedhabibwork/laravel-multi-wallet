<?php

namespace HWallet\LaravelMultiWallet\Services\FeeCalculators;

use HWallet\LaravelMultiWallet\Models\Wallet;

interface FeeCalculatorInterface
{
    /**
     * Calculate fee for a transaction
     */
    public function calculateTransactionFee(float $amount, Wallet $wallet, array $context = []): float;

    /**
     * Calculate fee for a transfer
     */
    public function calculateTransferFee(float $amount, Wallet $fromWallet, Wallet $toWallet, array $context = []): float;

    /**
     * Get fee configuration
     */
    public function getConfiguration(): array;

    /**
     * Check if calculator supports the operation
     */
    public function supports(string $operation): bool;
}
