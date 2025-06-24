<?php

namespace HWallet\LaravelMultiWallet\Services;

use HWallet\LaravelMultiWallet\Contracts\ExchangeRateProviderInterface;
use HWallet\LaravelMultiWallet\Contracts\WalletConfigurationInterface;
use HWallet\LaravelMultiWallet\Enums\BalanceType;

class WalletConfiguration implements WalletConfigurationInterface
{
    protected array $config;

    protected ?ExchangeRateProviderInterface $exchangeRateProvider = null;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'default_currency' => config('multi-wallet.default_currency', 'USD'),
            'wallet_limits' => [
                'max_balance' => config('multi-wallet.wallet_limits.max_balance', null),
                'min_balance' => config('multi-wallet.wallet_limits.min_balance', 0),
            ],
            'transaction_limits' => [
                'max_amount' => config('multi-wallet.transaction_limits.max_amount', null),
                'min_amount' => config('multi-wallet.transaction_limits.min_amount', 0.01),
                'daily_limit' => config('multi-wallet.transaction_limits.daily_limit', null),
            ],
            'balance_types' => config('multi-wallet.balance_types', BalanceType::toArray()),
            'uniqueness_enabled' => config('multi-wallet.uniqueness_enabled', true),
            'uniqueness_strategy' => config('multi-wallet.uniqueness_strategy', 'default'),
            'fee_calculation' => [
                'default_fee' => config('multi-wallet.fee_calculation.default_fee', 0),
                'percentage_based' => config('multi-wallet.fee_calculation.percentage_based', false),
            ],
            'metadata_schema' => config('multi-wallet.metadata_schema', []),
            'audit_logging_enabled' => config('multi-wallet.audit_logging_enabled', true),
            'exchange_rate_provider' => config('multi-wallet.exchange_rate_provider', DefaultExchangeRateProvider::class),
        ], $config);
    }

    /**
     * Get the default currency for wallets
     */
    public function getDefaultCurrency(): string
    {
        return $this->config['default_currency'];
    }

    /**
     * Get the exchange rate provider instance
     */
    public function getExchangeRateProvider(): ExchangeRateProviderInterface
    {
        if ($this->exchangeRateProvider === null) {
            $providerClass = $this->config['exchange_rate_provider'];

            if (is_string($providerClass)) {
                $this->exchangeRateProvider = app($providerClass);
            } elseif ($providerClass instanceof ExchangeRateProviderInterface) {
                $this->exchangeRateProvider = $providerClass;
            } else {
                $this->exchangeRateProvider = app(DefaultExchangeRateProvider::class);
            }
        }

        return $this->exchangeRateProvider;
    }

    /**
     * Get wallet limits configuration
     */
    public function getWalletLimits(): array
    {
        return $this->config['wallet_limits'];
    }

    /**
     * Get transaction limits configuration
     */
    public function getTransactionLimits(): array
    {
        return $this->config['transaction_limits'];
    }

    /**
     * Get enabled balance types
     */
    public function getBalanceTypes(): array
    {
        return $this->config['balance_types'];
    }

    /**
     * Check if wallet uniqueness is enabled
     */
    public function isUniquenessEnabled(): bool
    {
        return $this->config['uniqueness_enabled'];
    }

    /**
     * Get the uniqueness strategy
     */
    public function getUniquenessStrategy(): string
    {
        return $this->config['uniqueness_strategy'];
    }

    /**
     * Get fee calculation settings
     */
    public function getFeeCalculationSettings(): array
    {
        return $this->config['fee_calculation'];
    }

    /**
     * Get metadata schema validation rules
     */
    public function getMetadataSchema(): array
    {
        return $this->config['metadata_schema'];
    }

    /**
     * Check if audit logging is enabled
     */
    public function isAuditLoggingEnabled(): bool
    {
        return $this->config['audit_logging_enabled'];
    }

    /**
     * Set a configuration value
     */
    public function set(string $key, $value): void
    {
        $this->config[$key] = $value;

        // Reset exchange rate provider if it was changed
        if ($key === 'exchange_rate_provider') {
            $this->exchangeRateProvider = null;
        }
    }

    /**
     * Get a configuration value
     */
    public function get(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Get all configuration
     */
    public function all(): array
    {
        return $this->config;
    }

    /**
     * Merge configuration with new values
     */
    public function merge(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * Check if a balance type is enabled
     */
    public function isBalanceTypeEnabled(BalanceType|string $balanceType): bool
    {
        $balanceTypeValue = $balanceType instanceof BalanceType ? $balanceType->value : $balanceType;

        return in_array($balanceTypeValue, $this->getBalanceTypes());
    }

    /**
     * Get maximum balance limit
     */
    public function getMaxBalanceLimit(): ?float
    {
        return $this->config['wallet_limits']['max_balance'];
    }

    /**
     * Get minimum balance limit
     */
    public function getMinBalanceLimit(): float
    {
        return $this->config['wallet_limits']['min_balance'] ?? 0;
    }

    /**
     * Get maximum transaction amount
     */
    public function getMaxTransactionAmount(): ?float
    {
        return $this->config['transaction_limits']['max_amount'];
    }

    /**
     * Get minimum transaction amount
     */
    public function getMinTransactionAmount(): float
    {
        return $this->config['transaction_limits']['min_amount'] ?? 0.01;
    }

    /**
     * Get daily transaction limit
     */
    public function getDailyTransactionLimit(): ?float
    {
        return $this->config['transaction_limits']['daily_limit'];
    }
}
