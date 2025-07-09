<?php

namespace HWallet\LaravelMultiWallet\Helpers;

use HWallet\LaravelMultiWallet\Enums\BalanceType;
use HWallet\LaravelMultiWallet\Enums\TransactionType;
use HWallet\LaravelMultiWallet\Models\Wallet;
use HWallet\LaravelMultiWallet\Models\Transaction;
use HWallet\LaravelMultiWallet\Models\Transfer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Helper functions for wallet operations
 */
class WalletHelpers
{
    /**
     * Format currency amount with proper decimal places (static method)
     */
    public static function formatAmountStatic(float $amount, string $currency = 'USD', int $decimals = 2): string
    {
        // Validate currency first
        if (!self::isValidCurrency($currency)) {
            throw new InvalidArgumentException("Invalid currency code: {$currency}");
        }
        
        if (!self::isCurrencySupported($currency)) {
            throw new InvalidArgumentException("Unsupported currency: {$currency}");
        }
        
        $formatted = number_format($amount, $decimals, '.', ',');
        $symbol = self::getCurrencySymbol($currency);
        
        return match (strtoupper($currency)) {
            'JPY', 'KRW', 'VND' => $symbol . number_format($amount, 0, '.', ','), // No decimal places for some currencies
            default => $symbol . $formatted,
        };
    }

    /**
     * Format currency amount with proper decimal places (instance method)
     */
    public function formatAmount(float $amount, string $currency = 'USD', int $decimals = 2): string
    {
        return self::formatAmountStatic($amount, $currency, $decimals);
    }

    /**
     * Get currency symbol
     */
    public static function getCurrencySymbol(string $currency): string
    {
        if (!self::isValidCurrency($currency)) {
            throw new InvalidArgumentException("Invalid currency code format: {$currency}");
        }
        
        return match (strtoupper($currency)) {
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
            'CAD' => 'C$',
            'AUD' => 'A$',
            'CHF' => 'CHF',
            'CNY' => '¥',
            default => strtoupper($currency) . ' ',
        };
    }

    /**
     * Validate currency code format
     */
    public static function isValidCurrency(string $currency): bool
    {
        return preg_match('/^[A-Z]{3}$/', strtoupper($currency)) === 1;
    }

    /**
     * Get supported currencies list
     */
    public static function getSupportedCurrencies(): array
    {
        return config('multi-wallet.supported_currencies', [
            'USD', 'EUR', 'GBP', 'JPY', 'CAD', 'AUD', 'CHF', 'CNY'
        ]);
    }

    /**
     * Check if currency is supported
     */
    public static function isCurrencySupported(string $currency): bool
    {
        return in_array(strtoupper($currency), self::getSupportedCurrencies());
    }

    /**
     * Generate unique wallet slug
     */
    public static function generateWalletSlug(string $name, string $currency, ?int $holderId = null): string
    {
        $baseSlug = strtolower(trim($name)) . '-' . strtolower($currency);
        
        if ($holderId) {
            $baseSlug .= '-' . $holderId;
        }
        
        return preg_replace('/[^a-z0-9-]/', '-', $baseSlug);
    }

    /**
     * Calculate percentage of amount
     */
    public static function calculatePercentage(float $amount, float $percentage): float
    {
        return ($amount * $percentage) / 100;
    }

    /**
     * Round amount to specified decimal places
     */
    public static function roundAmount(float $amount, int $decimals = 2): float
    {
        return round($amount, $decimals);
    }

    /**
     * Validate amount is positive
     */
    public static function validatePositiveAmount(float $amount): bool
    {
        return $amount > 0;
    }

    /**
     * Get balance type enum from string
     */
    public static function getBalanceType(string $balanceType): BalanceType
    {
        return BalanceType::tryFrom($balanceType) ?? BalanceType::AVAILABLE;
    }

    /**
     * Get transaction type enum from string
     */
    public static function getTransactionType(string $transactionType): TransactionType
    {
        return TransactionType::tryFrom($transactionType) ?? TransactionType::CREDIT;
    }

    /**
     * Check if model has wallet trait
     */
    public static function hasWalletTrait(Model $model): bool
    {
        return method_exists($model, 'wallets');
    }

    /**
     * Get wallet holder type from model
     */
    public static function getHolderType(Model $model): string
    {
        return get_class($model);
    }

    /**
     * Validate wallet holder
     */
    public static function validateWalletHolder(Model $model): void
    {
        if (!self::hasWalletTrait($model)) {
            throw new InvalidArgumentException(
                'Model must use HasWallets trait: ' . get_class($model)
            );
        }
    }

    /**
     * Get wallet balance summary as array
     */
    public static function getBalanceSummary(Wallet $wallet): array
    {
        return [
            'available' => $wallet->getBalance(BalanceType::AVAILABLE),
            'pending' => $wallet->getBalance(BalanceType::PENDING),
            'frozen' => $wallet->getBalance(BalanceType::FROZEN),
            'trial' => $wallet->getBalance(BalanceType::TRIAL),
            'total' => $wallet->getTotalBalance(),
        ];
    }

    /**
     * Check if wallet has sufficient balance
     */
    public static function hasSufficientBalance(Wallet $wallet, float $amount, BalanceType $balanceType = BalanceType::AVAILABLE): bool
    {
        return $wallet->getBalance($balanceType) >= $amount;
    }

    /**
     * Get wallet statistics
     */
    public static function getWalletStatistics(Wallet $wallet): array
    {
        $transactions = $wallet->transactions;
        $transfers = $wallet->incomingTransfers->merge($wallet->outgoingTransfers);

        return [
            'total_transactions' => $transactions->count(),
            'total_credits' => $transactions->where('type', TransactionType::CREDIT)->sum('amount'),
            'total_debits' => $transactions->where('type', TransactionType::DEBIT)->sum('amount'),
            'total_transfers' => $transfers->count(),
            'total_transfers_sent' => $wallet->outgoingTransfers->count(),
            'total_transfers_received' => $wallet->incomingTransfers->count(),
            'current_balance' => $wallet->getTotalBalance(),
            'balance_summary' => self::getBalanceSummary($wallet),
            'created_at' => $wallet->created_at,
            'last_transaction_at' => $transactions->max('created_at'),
        ];
    }

    /**
     * Get user wallet summary across all currencies
     */
    public static function getUserWalletSummary(Model $user): array
    {
        self::validateWalletHolder($user);

        $wallets = $user->wallets;
        $summary = [];

        foreach ($wallets as $wallet) {
            $summary[$wallet->currency] = [
                'wallet_id' => $wallet->id,
                'wallet_name' => $wallet->name,
                'balance_summary' => self::getBalanceSummary($wallet),
                'statistics' => self::getWalletStatistics($wallet),
            ];
        }

        return $summary;
    }

    /**
     * Calculate transfer fee
     */
    public static function calculateTransferFee(float $amount, float $feePercentage = 0, float $fixedFee = 0): float
    {
        $percentageFee = self::calculatePercentage($amount, $feePercentage);
        return self::roundAmount($percentageFee + $fixedFee);
    }

    /**
     * Calculate net amount after fees
     */
    public static function calculateNetAmount(float $amount, float $feePercentage = 0, float $fixedFee = 0): float
    {
        $fee = self::calculateTransferFee($amount, $feePercentage, $fixedFee);
        return self::roundAmount($amount - $fee);
    }

    /**
     * Validate transaction metadata
     */
    public static function validateTransactionMetadata(array $metadata): bool
    {
        // Check for required fields if any
        $requiredFields = config('multi-wallet.transaction.required_metadata', []);
        
        foreach ($requiredFields as $field) {
            if (!isset($metadata[$field])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Sanitize transaction metadata
     */
    public static function sanitizeTransactionMetadata(array $metadata): array
    {
        // Remove sensitive fields
        $sensitiveFields = ['password', 'token', 'secret', 'key'];
        
        foreach ($sensitiveFields as $field) {
            unset($metadata[$field]);
        }

        // Limit metadata size
        $maxSize = config('multi-wallet.transaction.max_metadata_size', 1000);
        if (strlen(json_encode($metadata)) > $maxSize) {
            throw new InvalidArgumentException('Transaction metadata too large');
        }

        return $metadata;
    }

    /**
     * Get wallet by currency for user
     */
    public static function getWalletByCurrency(Model $user, string $currency): ?Wallet
    {
        self::validateWalletHolder($user);
        
        return $user->wallets()->where('currency', strtoupper($currency))->first();
    }

    /**
     * Get or create wallet by currency for user
     */
    public static function getOrCreateWalletByCurrency(Model $user, string $currency, ?string $name = null): Wallet
    {
        self::validateWalletHolder($user);
        
        $wallet = self::getWalletByCurrency($user, $currency);
        
        if (!$wallet) {
            $wallet = $user->createWallet($currency, $name ?? "{$currency} Wallet");
        }
        
        return $wallet;
    }

    /**
     * Check if wallet exists for user and currency
     */
    public static function walletExists(Model $user, string $currency): bool
    {
        return self::getWalletByCurrency($user, $currency) !== null;
    }

    /**
     * Get wallet balance for specific currency
     */
    public static function getBalanceForCurrency(Model $user, string $currency, BalanceType $balanceType = BalanceType::AVAILABLE): float
    {
        $wallet = self::getWalletByCurrency($user, $currency);
        
        return $wallet ? $wallet->getBalance($balanceType) : 0.0;
    }

    /**
     * Get total balance across all currencies
     */
    public static function getTotalBalanceAcrossCurrencies(Model $user, BalanceType $balanceType = BalanceType::AVAILABLE): array
    {
        self::validateWalletHolder($user);
        
        $balances = [];
        foreach ($user->wallets as $wallet) {
            $balances[$wallet->currency] = $wallet->getBalance($balanceType);
        }
        
        return $balances;
    }

    /**
     * Validate transfer amount limits
     */
    public static function validateTransferAmount(float $amount, ?float $minAmount = null, ?float $maxAmount = null): bool
    {
        $minAmount = $minAmount ?? config('multi-wallet.transaction_limits.min_amount', 0.01);
        $maxAmount = $maxAmount ?? config('multi-wallet.transaction_limits.max_amount');

        if ($amount < $minAmount) {
            return false;
        }

        if ($maxAmount && $amount > $maxAmount) {
            return false;
        }

        return true;
    }

    /**
     * Get wallet holder information
     */
    public static function getWalletHolderInfo(Wallet $wallet): array
    {
        return [
            'holder_type' => $wallet->holder_type,
            'holder_id' => $wallet->holder_id,
            'holder' => $wallet->holder,
        ];
    }

    /**
     * Check if wallet is active
     */
    public static function isWalletActive(Wallet $wallet): bool
    {
        return !$wallet->trashed() && $wallet->getTotalBalance() >= 0;
    }

    /**
     * Get wallet age in days
     */
    public static function getWalletAge(Wallet $wallet): int
    {
        return $wallet->created_at->diffInDays(now());
    }

    /**
     * Get recent transactions for wallet
     */
    public static function getRecentTransactions(Wallet $wallet, int $limit = 10): Collection
    {
        return $wallet->transactions()
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Get transaction history for date range
     */
    public static function getTransactionHistory(Wallet $wallet, string $startDate, string $endDate): Collection
    {
        return $wallet->transactions()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Calculate wallet performance metrics
     */
    public static function calculateWalletPerformance(Wallet $wallet, int $days = 30): array
    {
        $startDate = now()->subDays($days);
        $transactions = $wallet->transactions()
            ->where('created_at', '>=', $startDate)
            ->get();

        $totalCredits = $transactions->where('type', TransactionType::CREDIT)->sum('amount');
        $totalDebits = $transactions->where('type', TransactionType::DEBIT)->sum('amount');
        $netChange = $totalCredits - $totalDebits;

        return [
            'period_days' => $days,
            'total_transactions' => $transactions->count(),
            'total_credits' => $totalCredits,
            'total_debits' => $totalDebits,
            'net_change' => $netChange,
            'average_daily_change' => $netChange / $days,
            'transaction_frequency' => $transactions->count() / $days,
        ];
    }

    /**
     * Calculate transaction fee with different strategies
     */
    public function calculateTransactionFee(float $amount, string $strategy = 'percentage', $config = null): float
    {
        return match ($strategy) {
            'percentage' => ($amount * $config) / 100,
            'fixed' => $config,
            'tiered' => $this->calculateTieredFee($amount, $config),
            default => 0.0,
        };
    }

    /**
     * Calculate tiered fee
     */
    private function calculateTieredFee(float $amount, array $config): float
    {
        $tiers = $config['tiers'] ?? [];
        
        foreach ($tiers as $tier) {
            if ($amount >= $tier['min'] && ($tier['max'] === null || $amount < $tier['max'])) {
                return ($amount * $tier['rate']) / 100;
            }
        }
        
        // If no tier matches, use the highest tier
        $lastTier = end($tiers);
        if ($lastTier && $amount >= $lastTier['min']) {
            return ($amount * $lastTier['rate']) / 100;
        }
        
        return 0.0;
    }

    /**
     * Validate metadata
     */
    public function validateMetadata(array $metadata): bool
    {
        $sensitiveKeys = ['password', 'token', 'api_key', 'secret'];
        
        foreach ($metadata as $key => $value) {
            if (in_array(strtolower($key), $sensitiveKeys)) {
                return false;
            }
            
            if (is_string($value) && strlen($value) > 1000) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Calculate balance statistics for multiple wallets
     */
    public function calculateBalanceStatistics(array $wallets): array
    {
        if (empty($wallets)) {
            return ['total' => 0, 'average' => 0, 'min' => 0, 'max' => 0, 'count' => 0];
        }

        $balances = array_map(fn($wallet) => $wallet->getBalance(BalanceType::AVAILABLE), $wallets);
        
        return [
            'total' => array_sum($balances),
            'average' => array_sum($balances) / count($balances),
            'min' => min($balances),
            'max' => max($balances),
            'count' => count($balances),
        ];
    }

    /**
     * Add amounts with precision handling
     */
    public function addAmounts(float $amount1, float $amount2): float
    {
        return round($amount1 + $amount2, 8);
    }

    /**
     * Format multiple currencies
     */
    public function formatMultipleCurrencies(array $amounts): array
    {
        return array_map(function($item) {
            return self::formatAmountStatic($item['amount'], $item['currency']);
        }, $amounts);
    }

    /**
     * Format balance summary for display
     */
    public function formatBalanceSummary(Wallet $wallet): array
    {
        $summary = self::getBalanceSummary($wallet);
        
        return [
            'available' => self::formatAmountStatic($summary['available'], $wallet->currency),
            'pending' => self::formatAmountStatic($summary['pending'], $wallet->currency),
            'frozen' => self::formatAmountStatic($summary['frozen'], $wallet->currency),
            'trial' => self::formatAmountStatic($summary['trial'], $wallet->currency),
            'total' => self::formatAmountStatic($summary['total'], $wallet->currency),
        ];
    }
} 