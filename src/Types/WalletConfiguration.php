<?php

namespace HWallet\LaravelMultiWallet\Types;

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

    public function isUniquenessEnabled(): bool
    {
        return $this->config['uniqueness_enabled'] ?? true;
    }

    public function areEventsEnabled(): bool
    {
        return $this->config['enable_events'] ?? true;
    }

    public function isAuditLogEnabled(): bool
    {
        return $this->config['enable_audit_log'] ?? true;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return isset($this->config[$key]);
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

        return $config;
    }
}
