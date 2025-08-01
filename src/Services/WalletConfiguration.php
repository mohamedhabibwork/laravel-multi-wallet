<?php

namespace HWallet\LaravelMultiWallet\Services;

use HWallet\LaravelMultiWallet\Contracts\ExchangeRateProviderInterface;
use HWallet\LaravelMultiWallet\Contracts\WalletConfigurationInterface;
use HWallet\LaravelMultiWallet\Enums\BalanceType;
use HWallet\LaravelMultiWallet\Models\Transaction;
use HWallet\LaravelMultiWallet\Models\Transfer;
use HWallet\LaravelMultiWallet\Models\Wallet;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

class WalletConfiguration implements WalletConfigurationInterface
{
    protected array $config;

    protected ?ExchangeRateProviderInterface $exchangeRateProvider = null;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'default_currency' => config('multi-wallet.default_currency', 'USD'),
            'models' => config('multi-wallet.models', []),
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
                'fee_percentage' => config('multi-wallet.fee_calculation.fee_percentage', 0),
            ],
            'metadata_schema' => config('multi-wallet.metadata_schema', []),
            'audit_logging_enabled' => config('multi-wallet.audit_logging_enabled', true),
            'exchange_rate_provider' => config('multi-wallet.exchange_rate_provider', DefaultExchangeRateProvider::class),
            'events' => config('multi-wallet.events', []),
            'wallet_configuration' => config('multi-wallet.wallet_configuration', []),
            'webhook' => config('multi-wallet.webhook', []),
            'cache' => config('multi-wallet.cache', []),
            'supported_currencies' => config('multi-wallet.supported_currencies', []),
            'exchange_rates' => config('multi-wallet.exchange_rates', []),
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
     * Get model class for the specified type
     */
    public function getModelClass(string $type): string
    {
        return $this->config['models'][$type] ?? match ($type) {
            'wallet' => Wallet::class,
            'transaction' => Transaction::class,
            'transfer' => Transfer::class,
            default => throw new \InvalidArgumentException("Unknown model type: {$type}"),
        };
    }

    /**
     * Get all configured models
     */
    public function getModels(): array
    {
        return $this->config['models'];
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
     * Get transfer settings
     */
    public function getTransferSettings(): array
    {
        return $this->config['transfer_settings'] ?? [
            'max_fee_percentage' => 10,
            'default_fee_percentage' => 0,
            'enable_cross_currency' => true,
            'require_confirmation' => false,
        ];
    }

    /**
     * Get events configuration
     */
    public function getEventsConfiguration(): array
    {
        return $this->config['events'];
    }

    /**
     * Check if events are enabled
     */
    public function areEventsEnabled(): bool
    {
        return $this->config['events']['enabled'] ?? true;
    }

    /**
     * Get available events
     */
    public function getAvailableEvents(): array
    {
        return $this->config['events']['available_events'] ?? [];
    }

    /**
     * Get event listeners
     */
    public function getEventListeners(): array
    {
        return $this->config['events']['listeners'] ?? [];
    }

    /**
     * Get wallet configuration settings
     */
    public function getWalletConfiguration(): array
    {
        return $this->config['wallet_configuration'];
    }

    /**
     * Check if auto wallet creation is enabled
     */
    public function isAutoCreateWalletEnabled(): bool
    {
        return $this->config['wallet_configuration']['auto_create_wallet'] ?? true;
    }

    /**
     * Get default wallet name
     */
    public function getDefaultWalletName(): string
    {
        return $this->config['wallet_configuration']['default_wallet_name'] ?? 'default';
    }

    /**
     * Check if bulk operations are enabled
     */
    public function isBulkOperationsEnabled(): bool
    {
        return $this->config['wallet_configuration']['enable_bulk_operations'] ?? true;
    }

    /**
     * Get freeze rules
     */
    public function getFreezeRules(): array
    {
        return $this->config['wallet_configuration']['freeze_rules'] ?? [];
    }

    /**
     * Get notification settings
     */
    public function getNotificationSettings(): array
    {
        return $this->config['wallet_configuration']['notification_settings'] ?? [];
    }

    /**
     * Get security settings
     */
    public function getSecuritySettings(): array
    {
        return $this->config['wallet_configuration']['security_settings'] ?? [];
    }

    /**
     * Get webhook configuration
     */
    public function getWebhookConfiguration(): array
    {
        return $this->config['webhook'];
    }

    /**
     * Check if webhooks are enabled
     */
    public function areWebhooksEnabled(): bool
    {
        return $this->config['webhook']['enabled'] ?? false;
    }

    /**
     * Get cache configuration
     */
    public function getCacheConfiguration(): array
    {
        return $this->config['cache'];
    }

    /**
     * Check if caching is enabled
     */
    public function isCachingEnabled(): bool
    {
        return $this->config['cache']['enabled'] ?? false;
    }

    /**
     * Get cache TTL
     */
    public function getCacheTTL(): int
    {
        return $this->config['cache']['ttl'] ?? 3600;
    }

    /**
     * Get cache prefix
     */
    public function getCachePrefix(): string
    {
        return $this->config['cache']['prefix'] ?? 'wallet';
    }

    /**
     * Get supported currencies
     */
    public function getSupportedCurrencies(): array
    {
        return $this->config['supported_currencies'];
    }

    /**
     * Check if currency is supported
     */
    public function isCurrencySupported(string $currency): bool
    {
        return in_array(strtoupper($currency), $this->getSupportedCurrencies());
    }

    /**
     * Get custom exchange rates
     */
    public function getCustomExchangeRates(): array
    {
        return $this->config['exchange_rates'];
    }

    /**
     * Get exchange rate for currency pair
     */
    public function getExchangeRate(string $from, string $to): float
    {
        $key = "{$from}_{$to}";

        return $this->config['exchange_rates'][$key] ?? 1.0;
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

        // Fire configuration changed event
        if ($this->areEventsEnabled()) {
            Event::dispatch('wallet.configuration_changed', [
                'key' => $key,
                'value' => $value,
                'timestamp' => now(),
            ]);
        }
    }

    /**
     * Get a configuration value
     */
    public function get(string $key, $default = null)
    {
        return data_get($this->config, $key, $default);
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
        $this->config = array_merge_recursive($this->config, $config);
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

    /**
     * Check if confirmation is required
     */
    public function isConfirmationRequired(): bool
    {
        return $this->config['wallet_configuration']['security_settings']['require_confirmation'] ?? false;
    }

    /**
     * Get maximum failed attempts
     */
    public function getMaxFailedAttempts(): int
    {
        return $this->config['wallet_configuration']['security_settings']['max_failed_attempts'] ?? 5;
    }

    /**
     * Get lockout duration
     */
    public function getLockoutDuration(): int
    {
        return $this->config['wallet_configuration']['security_settings']['lockout_duration'] ?? 900;
    }

    /**
     * Check if auto freeze on suspicious activity is enabled
     */
    public function isAutoFreezeOnSuspiciousActivityEnabled(): bool
    {
        return $this->config['wallet_configuration']['freeze_rules']['auto_freeze_on_suspicious_activity'] ?? true;
    }

    /**
     * Check if auto freeze on limit exceeded is enabled
     */
    public function isAutoFreezeOnLimitExceededEnabled(): bool
    {
        return $this->config['wallet_configuration']['freeze_rules']['auto_freeze_on_limit_exceeded'] ?? false;
    }

    /**
     * Check if notification on balance change is enabled
     */
    public function isNotifyOnBalanceChangeEnabled(): bool
    {
        return $this->config['wallet_configuration']['notification_settings']['notify_on_balance_change'] ?? true;
    }

    /**
     * Check if notification on transaction is enabled
     */
    public function isNotifyOnTransactionEnabled(): bool
    {
        return $this->config['wallet_configuration']['notification_settings']['notify_on_transaction'] ?? true;
    }

    /**
     * Check if notification on transfer is enabled
     */
    public function isNotifyOnTransferEnabled(): bool
    {
        return $this->config['wallet_configuration']['notification_settings']['notify_on_transfer'] ?? true;
    }

    /**
     * Get cached configuration value
     */
    public function getCached(string $key, $default = null)
    {
        if (! $this->isCachingEnabled()) {
            return $this->get($key, $default);
        }

        $cacheKey = $this->getCachePrefix().'.config.'.$key;

        return Cache::remember($cacheKey, $this->getCacheTTL(), function () use ($key, $default) {
            return $this->get($key, $default);
        });
    }

    /**
     * Clear configuration cache
     */
    public function clearCache(): void
    {
        if ($this->isCachingEnabled()) {
            Cache::forget($this->getCachePrefix().'.config.*');
        }
    }

    /**
     * Validate configuration
     */
    public function validate(): array
    {
        $errors = [];

        // Validate default currency
        if (! $this->isCurrencySupported($this->getDefaultCurrency())) {
            $errors[] = "Default currency '{$this->getDefaultCurrency()}' is not supported.";
        }

        // Validate balance limits
        $maxBalance = $this->getMaxBalanceLimit();
        $minBalance = $this->getMinBalanceLimit();

        if ($maxBalance !== null && $minBalance > $maxBalance) {
            $errors[] = "Minimum balance ({$minBalance}) cannot be greater than maximum balance ({$maxBalance}).";
        }

        // Validate transaction limits
        $maxTransaction = $this->getMaxTransactionAmount();
        $minTransaction = $this->getMinTransactionAmount();

        if ($maxTransaction !== null && $minTransaction > $maxTransaction) {
            $errors[] = "Minimum transaction amount ({$minTransaction}) cannot be greater than maximum transaction amount ({$maxTransaction}).";
        }

        // Validate model classes
        foreach ($this->getModels() as $type => $class) {
            if (! class_exists($class)) {
                $errors[] = "Model class '{$class}' for type '{$type}' does not exist.";
            }
        }

        return $errors;
    }

    /**
     * Apply configuration from attributes
     */
    public function applyFromAttributes(array $attributes): void
    {
        foreach ($attributes as $key => $value) {
            if ($value !== null) {
                $this->set($key, $value);
            }
        }
    }

    /**
     * Get configuration for wallet creation
     */
    public function getWalletCreationConfig(): array
    {
        return [
            'default_currency' => $this->getDefaultCurrency(),
            'default_name' => $this->getDefaultWalletName(),
            'auto_create' => $this->isAutoCreateWalletEnabled(),
            'enable_events' => $this->areEventsEnabled(),
            'enable_audit_log' => $this->isAuditLoggingEnabled(),
            'limits' => $this->getWalletLimits(),
            'balance_types' => $this->getBalanceTypes(),
            'metadata_schema' => $this->getMetadataSchema(),
        ];
    }
}
