<?php

namespace HWallet\LaravelMultiWallet\Services\FeeCalculators;

use HWallet\LaravelMultiWallet\Models\Wallet;

class DefaultFeeCalculator implements FeeCalculatorInterface
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'transaction_fee' => 0,
            'transaction_fee_percentage' => 0,
            'transfer_fee' => 0,
            'transfer_fee_percentage' => 0,
            'currency_conversion_fee' => 0,
            'min_fee' => 0,
            'max_fee' => null,
        ], $config);
    }

    /**
     * Calculate fee for a transaction
     */
    public function calculateTransactionFee(float $amount, Wallet $wallet, array $context = []): float
    {
        $fee = $this->config['transaction_fee'];

        if ($this->config['transaction_fee_percentage'] > 0) {
            $fee += $amount * ($this->config['transaction_fee_percentage'] / 100);
        }

        return $this->applyFeeLimits($fee);
    }

    /**
     * Calculate fee for a transfer
     */
    public function calculateTransferFee(float $amount, Wallet $fromWallet, Wallet $toWallet, array $context = []): float
    {
        $fee = $this->config['transfer_fee'];

        if ($this->config['transfer_fee_percentage'] > 0) {
            $fee += $amount * ($this->config['transfer_fee_percentage'] / 100);
        }

        // Add currency conversion fee if different currencies
        if ($fromWallet->currency !== $toWallet->currency) {
            $fee += $this->config['currency_conversion_fee'];
        }

        return $this->applyFeeLimits($fee);
    }

    /**
     * Get fee configuration
     */
    public function getConfiguration(): array
    {
        return $this->config;
    }

    /**
     * Check if calculator supports the operation
     */
    public function supports(string $operation): bool
    {
        return in_array($operation, ['transaction', 'transfer']);
    }

    /**
     * Apply minimum and maximum fee limits
     */
    protected function applyFeeLimits(float $fee): float
    {
        $fee = max($fee, $this->config['min_fee']);

        if ($this->config['max_fee'] !== null) {
            $fee = min($fee, $this->config['max_fee']);
        }

        return $fee;
    }
}
