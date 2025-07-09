<?php

if (!function_exists('wallet_format_amount')) {
    /**
     * Format amount with currency
     */
    function wallet_format_amount(float $amount, string $currency = 'USD', int $decimals = 2): string
    {
        return \HWallet\LaravelMultiWallet\Helpers\WalletHelpers::formatAmountStatic($amount, $currency, $decimals);
    }
}

if (!function_exists('wallet_is_currency_supported')) {
    /**
     * Check if currency is supported
     */
    function wallet_is_currency_supported(string $currency): bool
    {
        return \HWallet\LaravelMultiWallet\Helpers\WalletHelpers::isCurrencySupported($currency);
    }
}

if (!function_exists('wallet_validate_amount')) {
    /**
     * Validate amount within limits
     */
    function wallet_validate_amount(float $amount, ?float $minAmount = null, ?float $maxAmount = null): bool
    {
        return \HWallet\LaravelMultiWallet\Helpers\WalletHelpers::validateTransferAmount($amount, $minAmount, $maxAmount);
    }
}

if (!function_exists('wallet_calculate_fee')) {
    /**
     * Calculate transfer fee
     */
    function wallet_calculate_fee(float $amount, float $feePercentage = 0, float $fixedFee = 0): float
    {
        return \HWallet\LaravelMultiWallet\Helpers\WalletHelpers::calculateTransferFee($amount, $feePercentage, $fixedFee);
    }
}

if (!function_exists('wallet_round_amount')) {
    /**
     * Round amount to specified decimals
     */
    function wallet_round_amount(float $amount, int $decimals = 2): float
    {
        return \HWallet\LaravelMultiWallet\Helpers\WalletHelpers::roundAmount($amount, $decimals);
    }
}

if (!function_exists('wallet_calculate_percentage')) {
    /**
     * Calculate percentage of amount
     */
    function wallet_calculate_percentage(float $amount, float $percentage): float
    {
        return \HWallet\LaravelMultiWallet\Helpers\WalletHelpers::calculatePercentage($amount, $percentage);
    }
}

if (!function_exists('wallet_format_balance_summary')) {
    /**
     * Format balance summary for display
     */
    function wallet_format_balance_summary($wallet): array
    {
        return app(\HWallet\LaravelMultiWallet\Helpers\WalletHelpers::class)->formatBalanceSummary($wallet);
    }
}

if (!function_exists('wallet_get_user_summary')) {
    /**
     * Get user wallet summary
     */
    function wallet_get_user_summary($user): array
    {
        $summary = \HWallet\LaravelMultiWallet\Helpers\WalletHelpers::getUserWalletSummary($user);
        
        $totalBalance = 0;
        foreach ($summary as $currency => $data) {
            $totalBalance += $data['balance_summary']['total'] ?? 0;
        }
        
        return [
            'wallets' => $summary,
            'total_balance' => $totalBalance,
            'currencies' => array_keys($summary),
        ];
    }
} 