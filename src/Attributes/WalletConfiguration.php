<?php

namespace HWallet\LaravelMultiWallet\Attributes;

use Attribute;
use HWallet\LaravelMultiWallet\Types\WalletConfiguration as WalletConfigurationType;

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
        public ?array $freezeRules = null,
        public ?array $transactionLimits = null,
        public ?array $walletLimits = null,
        public ?bool $enableBulkOperations = null,
        public ?array $webhookSettings = null,
        public ?array $notificationSettings = null,
        public ?array $securitySettings = null,
        public ?bool $enableCache = null,
        public ?int $cacheTtl = null,
        public ?string $cachePrefix = null,
        public ?bool $autoFreezeOnSuspiciousActivity = null,
        public ?bool $autoFreezeOnLimitExceeded = null,
        public ?bool $requireConfirmation = null,
        public ?int $maxFailedAttempts = null,
        public ?int $lockoutDuration = null,
        public ?bool $notifyOnBalanceChange = null,
        public ?bool $notifyOnTransaction = null,
        public ?bool $notifyOnTransfer = null,
        public ?float $defaultFee = null,
        public ?bool $feePercentageBased = null,
        public ?float $feePercentage = null,
        public ?float $maxBalance = null,
        public ?float $minBalance = null,
        public ?float $maxTransactionAmount = null,
        public ?float $minTransactionAmount = null,
        public ?float $dailyTransactionLimit = null
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
            'fee_configuration' => $this->buildFeeConfiguration(),
            'uniqueness_enabled' => $this->uniquenessEnabled,
            'uniqueness_strategy' => $this->uniquenessStrategy,
            'exchange_rate_config' => $this->exchangeRateConfig,
            'freeze_rules' => $this->buildFreezeRules(),
            'transaction_limits' => $this->buildTransactionLimits(),
            'wallet_limits' => $this->buildWalletLimits(),
            'enable_bulk_operations' => $this->enableBulkOperations,
            'webhook_settings' => $this->webhookSettings,
            'notification_settings' => $this->buildNotificationSettings(),
            'security_settings' => $this->buildSecuritySettings(),
            'cache' => $this->buildCacheSettings(),
        ], fn ($value) => $value !== null);
    }

    /**
     * Create WalletConfiguration type from attributes
     */
    public function toWalletConfigurationType(): WalletConfigurationType
    {
        return new WalletConfigurationType($this->toArray());
    }

    /**
     * Apply configuration to existing WalletConfiguration type
     */
    public function applyTo(WalletConfigurationType $config): void
    {
        $config->merge($this->toArray());
    }

    /**
     * Build fee configuration from individual properties
     */
    private function buildFeeConfiguration(): ?array
    {
        $config = $this->feeConfiguration ?? [];

        if ($this->defaultFee !== null) {
            $config['default_fee'] = $this->defaultFee;
        }

        if ($this->feePercentageBased !== null) {
            $config['percentage_based'] = $this->feePercentageBased;
        }

        if ($this->feePercentage !== null) {
            $config['fee_percentage'] = $this->feePercentage;
        }

        return empty($config) ? null : $config;
    }

    /**
     * Build freeze rules from individual properties
     */
    private function buildFreezeRules(): ?array
    {
        $rules = $this->freezeRules ?? [];

        if ($this->autoFreezeOnSuspiciousActivity !== null) {
            $rules['auto_freeze_on_suspicious_activity'] = $this->autoFreezeOnSuspiciousActivity;
        }

        if ($this->autoFreezeOnLimitExceeded !== null) {
            $rules['auto_freeze_on_limit_exceeded'] = $this->autoFreezeOnLimitExceeded;
        }

        return empty($rules) ? null : $rules;
    }

    /**
     * Build transaction limits from individual properties
     */
    private function buildTransactionLimits(): ?array
    {
        $limits = $this->transactionLimits ?? [];

        if ($this->maxTransactionAmount !== null) {
            $limits['max_amount'] = $this->maxTransactionAmount;
        }

        if ($this->minTransactionAmount !== null) {
            $limits['min_amount'] = $this->minTransactionAmount;
        }

        if ($this->dailyTransactionLimit !== null) {
            $limits['daily_limit'] = $this->dailyTransactionLimit;
        }

        return empty($limits) ? null : $limits;
    }

    /**
     * Build wallet limits from individual properties
     */
    private function buildWalletLimits(): ?array
    {
        $limits = $this->walletLimits ?? [];

        if ($this->maxBalance !== null) {
            $limits['max_balance'] = $this->maxBalance;
        }

        if ($this->minBalance !== null) {
            $limits['min_balance'] = $this->minBalance;
        }

        return empty($limits) ? null : $limits;
    }

    /**
     * Build notification settings from individual properties
     */
    private function buildNotificationSettings(): ?array
    {
        $settings = $this->notificationSettings ?? [];

        if ($this->notifyOnBalanceChange !== null) {
            $settings['notify_on_balance_change'] = $this->notifyOnBalanceChange;
        }

        if ($this->notifyOnTransaction !== null) {
            $settings['notify_on_transaction'] = $this->notifyOnTransaction;
        }

        if ($this->notifyOnTransfer !== null) {
            $settings['notify_on_transfer'] = $this->notifyOnTransfer;
        }

        return empty($settings) ? null : $settings;
    }

    /**
     * Build security settings from individual properties
     */
    private function buildSecuritySettings(): ?array
    {
        $settings = $this->securitySettings ?? [];

        if ($this->requireConfirmation !== null) {
            $settings['require_confirmation'] = $this->requireConfirmation;
        }

        if ($this->maxFailedAttempts !== null) {
            $settings['max_failed_attempts'] = $this->maxFailedAttempts;
        }

        if ($this->lockoutDuration !== null) {
            $settings['lockout_duration'] = $this->lockoutDuration;
        }

        return empty($settings) ? null : $settings;
    }

    /**
     * Build cache settings from individual properties
     */
    private function buildCacheSettings(): ?array
    {
        $settings = [];

        if ($this->enableCache !== null) {
            $settings['enabled'] = $this->enableCache;
        }

        if ($this->cacheTtl !== null) {
            $settings['ttl'] = $this->cacheTtl;
        }

        if ($this->cachePrefix !== null) {
            $settings['prefix'] = $this->cachePrefix;
        }

        return empty($settings) ? null : $settings;
    }

    /**
     * Create attribute from WalletConfiguration type
     */
    public static function fromWalletConfigurationType(WalletConfigurationType $config): self
    {
        $configArray = $config->toArray();

        return new self(
            defaultCurrency: $configArray['default_currency'] ?? null,
            allowedCurrencies: $configArray['allowed_currencies'] ?? null,
            balanceTypes: $configArray['balance_types'] ?? null,
            autoCreateWallet: $configArray['auto_create_wallet'] ?? null,
            walletName: $configArray['wallet_name'] ?? null,
            metadata: $configArray['metadata'] ?? null,
            limits: $configArray['limits'] ?? null,
            enableEvents: $configArray['enable_events'] ?? null,
            enableAuditLog: $configArray['enable_audit_log'] ?? null,
            feeConfiguration: $configArray['fee_configuration'] ?? null,
            uniquenessEnabled: $configArray['uniqueness_enabled'] ?? null,
            uniquenessStrategy: $configArray['uniqueness_strategy'] ?? null,
            exchangeRateConfig: $configArray['exchange_rate_config'] ?? null,
            freezeRules: $configArray['freeze_rules'] ?? null,
            transactionLimits: $configArray['transaction_limits'] ?? null,
            walletLimits: $configArray['wallet_limits'] ?? null,
            enableBulkOperations: $configArray['enable_bulk_operations'] ?? null,
            webhookSettings: $configArray['webhook_settings'] ?? null,
            notificationSettings: $configArray['notification_settings'] ?? null,
            securitySettings: $configArray['security_settings'] ?? null,
            enableCache: $configArray['cache']['enabled'] ?? null,
            cacheTtl: $configArray['cache']['ttl'] ?? null,
            cachePrefix: $configArray['cache']['prefix'] ?? null,
            autoFreezeOnSuspiciousActivity: $configArray['freeze_rules']['auto_freeze_on_suspicious_activity'] ?? null,
            autoFreezeOnLimitExceeded: $configArray['freeze_rules']['auto_freeze_on_limit_exceeded'] ?? null,
            requireConfirmation: $configArray['security_settings']['require_confirmation'] ?? null,
            maxFailedAttempts: $configArray['security_settings']['max_failed_attempts'] ?? null,
            lockoutDuration: $configArray['security_settings']['lockout_duration'] ?? null,
            notifyOnBalanceChange: $configArray['notification_settings']['notify_on_balance_change'] ?? null,
            notifyOnTransaction: $configArray['notification_settings']['notify_on_transaction'] ?? null,
            notifyOnTransfer: $configArray['notification_settings']['notify_on_transfer'] ?? null,
            defaultFee: $configArray['fee_configuration']['default_fee'] ?? null,
            feePercentageBased: $configArray['fee_configuration']['percentage_based'] ?? null,
            feePercentage: $configArray['fee_configuration']['fee_percentage'] ?? null,
            maxBalance: $configArray['wallet_limits']['max_balance'] ?? null,
            minBalance: $configArray['wallet_limits']['min_balance'] ?? null,
            maxTransactionAmount: $configArray['transaction_limits']['max_amount'] ?? null,
            minTransactionAmount: $configArray['transaction_limits']['min_amount'] ?? null,
            dailyTransactionLimit: $configArray['transaction_limits']['daily_limit'] ?? null
        );
    }

    /**
     * Merge with another WalletConfiguration attribute
     */
    public function mergeWith(self $other): self
    {
        $merged = clone $this;

        // Merge all properties, prioritizing the other's values when not null
        foreach (get_object_vars($other) as $property => $value) {
            if ($value !== null) {
                $merged->$property = $value;
            }
        }

        return $merged;
    }

    /**
     * Validate the configuration
     */
    public function validate(): array
    {
        $errors = [];

        // Validate currency format
        if ($this->defaultCurrency && ! preg_match('/^[A-Z]{3}$/', $this->defaultCurrency)) {
            $errors[] = "Invalid default currency format: {$this->defaultCurrency}";
        }

        // Validate allowed currencies
        if ($this->allowedCurrencies) {
            foreach ($this->allowedCurrencies as $currency) {
                if (! preg_match('/^[A-Z]{3}$/', $currency)) {
                    $errors[] = "Invalid currency format: {$currency}";
                }
            }
        }

        // Validate numeric values
        if ($this->defaultFee !== null && $this->defaultFee < 0) {
            $errors[] = 'Default fee cannot be negative';
        }

        if ($this->feePercentage !== null && ($this->feePercentage < 0 || $this->feePercentage > 100)) {
            $errors[] = 'Fee percentage must be between 0 and 100';
        }

        if ($this->maxBalance !== null && $this->maxBalance < 0) {
            $errors[] = 'Maximum balance cannot be negative';
        }

        if ($this->minBalance !== null && $this->minBalance < 0) {
            $errors[] = 'Minimum balance cannot be negative';
        }

        if ($this->maxTransactionAmount !== null && $this->maxTransactionAmount < 0) {
            $errors[] = 'Maximum transaction amount cannot be negative';
        }

        if ($this->minTransactionAmount !== null && $this->minTransactionAmount < 0) {
            $errors[] = 'Minimum transaction amount cannot be negative';
        }

        if ($this->dailyTransactionLimit !== null && $this->dailyTransactionLimit < 0) {
            $errors[] = 'Daily transaction limit cannot be negative';
        }

        // Validate balance limits consistency
        if ($this->maxBalance !== null && $this->minBalance !== null && $this->minBalance > $this->maxBalance) {
            $errors[] = 'Minimum balance cannot be greater than maximum balance';
        }

        // Validate transaction limits consistency
        if ($this->maxTransactionAmount !== null && $this->minTransactionAmount !== null && $this->minTransactionAmount > $this->maxTransactionAmount) {
            $errors[] = 'Minimum transaction amount cannot be greater than maximum transaction amount';
        }

        // Validate security settings
        if ($this->maxFailedAttempts !== null && $this->maxFailedAttempts < 1) {
            $errors[] = 'Maximum failed attempts must be at least 1';
        }

        if ($this->lockoutDuration !== null && $this->lockoutDuration < 0) {
            $errors[] = 'Lockout duration cannot be negative';
        }

        // Validate cache settings
        if ($this->cacheTtl !== null && $this->cacheTtl < 0) {
            $errors[] = 'Cache TTL cannot be negative';
        }

        return $errors;
    }
}
