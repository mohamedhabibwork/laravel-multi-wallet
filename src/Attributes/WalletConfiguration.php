<?php

namespace HWallet\LaravelMultiWallet\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY)]
class WalletConfiguration
{
    public function __construct(
        public ?string $defaultCurrency = null,
        public ?array $allowedCurrencies = null,
        public ?array $balanceTypes = null,
        public ?bool $autoCreateWallet = null,
        public ?string $walletName = null,
        public ?array $metadata = null,
        public ?array $limits = null,
        public ?bool $enableEvents = null,
        public ?bool $enableAuditLog = null,
        public ?array $feeConfiguration = null,
        public ?bool $uniquenessEnabled = null,
        public ?string $uniquenessStrategy = null,
        public ?array $exchangeRateConfig = null,
        public ?string $freezeRules = null,
        public ?array $transactionLimits = null,
        public ?array $walletLimits = null,
        public ?bool $enableBulkOperations = null,
        public ?array $webhookSettings = null,
        public ?array $notificationSettings = null,
        public ?array $securitySettings = null
    ) {}

    /**
     * Get configuration as array
     */
    public function toArray(): array
    {
        return array_filter([
            'default_currency' => $this->defaultCurrency,
            'allowed_currencies' => $this->allowedCurrencies,
            'balance_types' => $this->balanceTypes,
            'auto_create_wallet' => $this->autoCreateWallet,
            'wallet_name' => $this->walletName,
            'metadata' => $this->metadata,
            'limits' => $this->limits,
            'enable_events' => $this->enableEvents,
            'enable_audit_log' => $this->enableAuditLog,
            'fee_configuration' => $this->feeConfiguration,
            'uniqueness_enabled' => $this->uniquenessEnabled,
            'uniqueness_strategy' => $this->uniquenessStrategy,
            'exchange_rate_config' => $this->exchangeRateConfig,
            'freeze_rules' => $this->freezeRules,
            'transaction_limits' => $this->transactionLimits,
            'wallet_limits' => $this->walletLimits,
            'enable_bulk_operations' => $this->enableBulkOperations,
            'webhook_settings' => $this->webhookSettings,
            'notification_settings' => $this->notificationSettings,
            'security_settings' => $this->securitySettings,
        ], fn ($value) => $value !== null);
    }
}
