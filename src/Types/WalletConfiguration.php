<?php

namespace HWallet\LaravelMultiWallet\Types;

use HWallet\LaravelMultiWallet\Enums\BalanceType;
use InvalidArgumentException;

/**
 * Wallet configuration value object
 */
class WalletConfiguration
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $this->validateConfig($config);
    }

    public function getDefaultCurrency(): string
    {
        return $this->config['default_currency'] ?? 'USD';
    }

    public function getAllowedCurrencies(): array
    {
        return $this->config['allowed_currencies'] ?? [];
    }

    public function isCurrencyAllowed(string $currency): bool
    {
        return in_array(strtoupper($currency), $this->getAllowedCurrencies());
    }

    public function getTransactionLimits(): array
    {
        return $this->config['transaction_limits'] ?? [];
    }

    public function getWalletLimits(): array
    {
        return $this->config['wallet_limits'] ?? [];
    }

    public function getMaxTransactionAmount(): ?float
    {
        return $this->getTransactionLimits()['max_amount'] ?? null;
    }

    public function getMinTransactionAmount(): float
    {
        return $this->getTransactionLimits()['min_amount'] ?? 0.01;
    }

    public function getMaxBalance(): ?float
    {
        return $this->getWalletLimits()['max_balance'] ?? null;
    }

    public function getMinBalance(): float
    {
        return $this->getWalletLimits()['min_balance'] ?? 0;
    }

    public function isUniquenessEnabled(): bool
    {
        return $this->config['uniqueness_enabled'] ?? true;
    }

    public function getUniquenessStrategy(): string
    {
        return $this->config['uniqueness_strategy'] ?? 'default';
    }

    public function areEventsEnabled(): bool
    {
        return $this->config['enable_events'] ?? true;
    }

    public function isAuditLogEnabled(): bool
    {
        return $this->config['enable_audit_log'] ?? true;
    }

    public function isAutoCreateWalletEnabled(): bool
    {
        return $this->config['auto_create_wallet'] ?? true;
    }

    public function getDefaultWalletName(): string
    {
        return $this->config['wallet_name'] ?? 'default';
    }

    public function getMetadata(): array
    {
        return $this->config['metadata'] ?? [];
    }

    public function getBalanceTypes(): array
    {
        return $this->config['balance_types'] ?? BalanceType::toArray();
    }

    public function isBalanceTypeEnabled(BalanceType|string $balanceType): bool
    {
        $balanceTypeValue = $balanceType instanceof BalanceType ? $balanceType->value : $balanceType;

        return in_array($balanceTypeValue, $this->getBalanceTypes());
    }

    public function getFeeConfiguration(): array
    {
        return $this->config['fee_configuration'] ?? [];
    }

    public function getDefaultFee(): float
    {
        return $this->getFeeConfiguration()['default_fee'] ?? 0;
    }

    public function isFeePercentageBased(): bool
    {
        return $this->getFeeConfiguration()['percentage_based'] ?? false;
    }

    public function getFeePercentage(): float
    {
        return $this->getFeeConfiguration()['fee_percentage'] ?? 0;
    }

    public function getExchangeRateConfig(): array
    {
        return $this->config['exchange_rate_config'] ?? [];
    }

    public function getFreezeRules(): array
    {
        return $this->config['freeze_rules'] ?? [];
    }

    public function isAutoFreezeOnSuspiciousActivityEnabled(): bool
    {
        return $this->config['freeze_rules']['auto_freeze_on_suspicious_activity'] ?? true;
    }

    public function isAutoFreezeOnLimitExceededEnabled(): bool
    {
        return $this->config['freeze_rules']['auto_freeze_on_limit_exceeded'] ?? false;
    }

    public function isBulkOperationsEnabled(): bool
    {
        return $this->config['enable_bulk_operations'] ?? true;
    }

    public function getWebhookSettings(): array
    {
        return $this->config['webhook_settings'] ?? [];
    }

    public function areWebhooksEnabled(): bool
    {
        return $this->getWebhookSettings()['enabled'] ?? false;
    }

    public function getWebhookUrl(): ?string
    {
        return $this->getWebhookSettings()['url'] ?? null;
    }

    public function getWebhookSecret(): ?string
    {
        return $this->getWebhookSettings()['secret'] ?? null;
    }

    public function getNotificationSettings(): array
    {
        return $this->config['notification_settings'] ?? [];
    }

    public function isNotifyOnBalanceChangeEnabled(): bool
    {
        return $this->getNotificationSettings()['notify_on_balance_change'] ?? true;
    }

    public function isNotifyOnTransactionEnabled(): bool
    {
        return $this->getNotificationSettings()['notify_on_transaction'] ?? true;
    }

    public function isNotifyOnTransferEnabled(): bool
    {
        return $this->getNotificationSettings()['notify_on_transfer'] ?? true;
    }

    public function getSecuritySettings(): array
    {
        return $this->config['security_settings'] ?? [];
    }

    public function isConfirmationRequired(): bool
    {
        return $this->getSecuritySettings()['require_confirmation'] ?? false;
    }

    public function getMaxFailedAttempts(): int
    {
        return $this->getSecuritySettings()['max_failed_attempts'] ?? 5;
    }

    public function getLockoutDuration(): int
    {
        return $this->getSecuritySettings()['lockout_duration'] ?? 900;
    }

    public function getDailyTransactionLimit(): ?float
    {
        return $this->getTransactionLimits()['daily_limit'] ?? null;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->config, $key, $default);
    }

    public function has(string $key): bool
    {
        return data_get($this->config, $key) !== null;
    }

    public function set(string $key, mixed $value): void
    {
        data_set($this->config, $key, $value);
    }

    public function merge(array $config): void
    {
        $this->config = array_merge_recursive($this->config, $config);
    }

    public function toArray(): array
    {
        return $this->config;
    }

    /**
     * Create configuration from global config
     */
    public static function fromGlobalConfig(): self
    {
        return new self([
            'default_currency' => config('multi-wallet.default_currency'),
            'allowed_currencies' => config('multi-wallet.supported_currencies'),
            'transaction_limits' => config('multi-wallet.transaction_limits'),
            'wallet_limits' => config('multi-wallet.wallet_limits'),
            'balance_types' => config('multi-wallet.balance_types'),
            'uniqueness_enabled' => config('multi-wallet.uniqueness_enabled'),
            'uniqueness_strategy' => config('multi-wallet.uniqueness_strategy'),
            'enable_events' => config('multi-wallet.events.enabled'),
            'enable_audit_log' => config('multi-wallet.audit_logging_enabled'),
            'auto_create_wallet' => config('multi-wallet.wallet_configuration.auto_create_wallet'),
            'wallet_name' => config('multi-wallet.wallet_configuration.default_wallet_name'),
            'fee_configuration' => config('multi-wallet.fee_calculation'),
            'exchange_rate_config' => config('multi-wallet.exchange_rates'),
            'freeze_rules' => config('multi-wallet.wallet_configuration.freeze_rules'),
            'enable_bulk_operations' => config('multi-wallet.wallet_configuration.enable_bulk_operations'),
            'webhook_settings' => config('multi-wallet.webhook'),
            'notification_settings' => config('multi-wallet.wallet_configuration.notification_settings'),
            'security_settings' => config('multi-wallet.wallet_configuration.security_settings'),
        ]);
    }

    /**
     * Create configuration for specific wallet
     */
    public static function forWallet(array $walletConfig = []): self
    {
        $globalConfig = self::fromGlobalConfig();
        $globalConfig->merge($walletConfig);

        return $globalConfig;
    }

    private function validateConfig(array $config): array
    {
        // Validate currency codes
        if (isset($config['allowed_currencies']) && is_array($config['allowed_currencies'])) {
            foreach ($config['allowed_currencies'] as $currency) {
                if (! preg_match('/^[A-Z]{3}$/', strtoupper($currency))) {
                    throw new InvalidArgumentException("Invalid currency code: {$currency}");
                }
            }
        }

        // Validate amounts are positive
        if (isset($config['transaction_limits']['min_amount']) && $config['transaction_limits']['min_amount'] < 0) {
            throw new InvalidArgumentException('Minimum transaction amount cannot be negative');
        }

        if (isset($config['wallet_limits']['max_balance']) && $config['wallet_limits']['max_balance'] < 0) {
            throw new InvalidArgumentException('Maximum wallet balance cannot be negative');
        }

        if (isset($config['wallet_limits']['min_balance']) && $config['wallet_limits']['min_balance'] < 0) {
            throw new InvalidArgumentException('Minimum wallet balance cannot be negative');
        }

        // Validate balance types
        if (isset($config['balance_types']) && is_array($config['balance_types'])) {
            $validTypes = BalanceType::toArray();
            foreach ($config['balance_types'] as $type) {
                if (! in_array($type, $validTypes)) {
                    throw new InvalidArgumentException("Invalid balance type: {$type}");
                }
            }
        }

        // Validate fee configuration
        if (isset($config['fee_configuration'])) {
            if (isset($config['fee_configuration']['default_fee']) && $config['fee_configuration']['default_fee'] < 0) {
                throw new InvalidArgumentException('Default fee cannot be negative');
            }

            if (isset($config['fee_configuration']['fee_percentage']) &&
                ($config['fee_configuration']['fee_percentage'] < 0 || $config['fee_configuration']['fee_percentage'] > 100)) {
                throw new InvalidArgumentException('Fee percentage must be between 0 and 100');
            }
        }

        // Validate security settings
        if (isset($config['security_settings'])) {
            if (isset($config['security_settings']['max_failed_attempts']) && $config['security_settings']['max_failed_attempts'] < 1) {
                throw new InvalidArgumentException('Maximum failed attempts must be at least 1');
            }

            if (isset($config['security_settings']['lockout_duration']) && $config['security_settings']['lockout_duration'] < 0) {
                throw new InvalidArgumentException('Lockout duration cannot be negative');
            }
        }

        return $config;
    }
}
