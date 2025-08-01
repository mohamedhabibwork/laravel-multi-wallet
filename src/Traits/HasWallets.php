<?php

namespace HWallet\LaravelMultiWallet\Traits;

use HWallet\LaravelMultiWallet\Attributes\WalletConfiguration;
use HWallet\LaravelMultiWallet\Contracts\WalletConfigurationInterface;
use HWallet\LaravelMultiWallet\Enums\BalanceType;
use HWallet\LaravelMultiWallet\Exceptions\WalletNotFoundException;
use HWallet\LaravelMultiWallet\Models\Transaction;
use HWallet\LaravelMultiWallet\Models\Transfer;
use HWallet\LaravelMultiWallet\Models\Wallet;
use HWallet\LaravelMultiWallet\Services\BulkWalletManager;
use HWallet\LaravelMultiWallet\Services\WalletManager;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use ReflectionClass;

trait HasWallets
{
    /**
     * Cached wallet configuration
     */
    protected ?array $walletConfiguration = null;

    /**
     * Get all wallets for this model
     */
    public function wallets(): MorphMany
    {
        return $this->morphMany(Wallet::class, 'holder');
    }

    /**
     * Get transfers where this model is the sender
     */
    public function sentTransfers(): MorphMany
    {
        return $this->morphMany(Transfer::class, 'from');
    }

    /**
     * Get transfers where this model is the receiver
     */
    public function receivedTransfers(): MorphMany
    {
        return $this->morphMany(Transfer::class, 'to');
    }

    /**
     * Alias for sentTransfers (for compatibility)
     */
    public function getTransfersFromAttribute()
    {
        return $this->sentTransfers;
    }

    /**
     * Alias for receivedTransfers (for compatibility)
     */
    public function getTransfersToAttribute()
    {
        return $this->receivedTransfers;
    }

    /**
     * Create a new wallet for this model
     */
    public function createWallet(string $currency, ?string $name = null, array $attributes = []): Wallet
    {
        return app(WalletManager::class)->create($this, $currency, $name, $attributes);
    }

    /**
     * Get or create a wallet for this model
     */
    public function getOrCreateWallet(string $currency, ?string $name = null, array $attributes = []): Wallet
    {
        return $this->getWallet($currency, $name) ?? $this->createWallet($currency, $name, $attributes);
    }

    /**
     * Get a specific wallet by currency and optionally by name
     */
    public function getWallet(string $currency, ?string $name = null): ?Wallet
    {
        $query = $this->wallets()->where('currency', $currency);

        if ($name !== null) {
            $query->where('name', $name);
        }

        return $query->first();
    }

    /**
     * Get the default wallet for a currency
     */
    public function getDefaultWallet(string $currency): ?Wallet
    {
        return $this->wallets()
            ->where('currency', $currency)
            ->whereNull('name')
            ->first();
    }

    /**
     * Get all wallets for a specific currency
     */
    public function getWalletsByCurrency(string $currency)
    {
        return $this->wallets()->where('currency', $currency)->get();
    }

    /**
     * Check if this model has a wallet for the specified currency
     */
    public function hasWallet(string $currency, ?string $name = null): bool
    {
        return $this->getWallet($currency, $name) !== null;
    }

    /**
     * Get balance for a specific currency and balance type
     */
    public function getBalance(string $currency, BalanceType|string $balanceType = 'available'): float
    {
        $wallet = $this->getWallet($currency) ?? $this->getDefaultWallet($currency);

        if (! $wallet) {
            return 0.0;
        }

        return $wallet->getBalance($balanceType);
    }

    /**
     * Credit a wallet for a specific currency
     */
    public function creditWallet(string $currency, float $amount, string $balanceType = 'available', array $meta = []): Transaction
    {
        $wallet = $this->getOrCreateWallet($currency);

        return $wallet->credit($amount, $balanceType, $meta);
    }

    /**
     * Debit a wallet for a specific currency
     */
    public function debitWallet(string $currency, float $amount, string $balanceType = 'available', array $meta = []): Transaction
    {
        $wallet = $this->getWallet($currency);

        if (! $wallet) {
            throw new WalletNotFoundException("Wallet not found for currency {$currency}");
        }

        return $wallet->debit($amount, $balanceType, $meta);
    }

    /**
     * Check if this model can afford a specific amount
     */
    public function canAfford(float $amount, string $currency, string $balanceType = 'available'): bool
    {
        $wallet = $this->getWallet($currency);

        if (! $wallet) {
            return false;
        }

        return $wallet->canDebit($amount, $balanceType);
    }

    /**
     * Transfer funds to another model
     */
    public function transferTo(
        $recipient,
        float $amount,
        string $currency,
        array $options = []
    ): Transfer {
        $fromWallet = $this->getWallet($currency);

        if (! $fromWallet) {
            throw new WalletNotFoundException("Sender wallet not found for currency {$currency}");
        }

        // Check if recipient has an existing wallet with a different currency
        $existingWallet = $recipient->wallets()->first();
        if ($existingWallet && $existingWallet->currency !== $currency) {
            // Use the existing wallet's currency for currency conversion
            $toWallet = $existingWallet;
        } else {
            // Create or get wallet with the same currency
            $toWallet = $recipient->getOrCreateWallet($currency);
        }

        return app(WalletManager::class)->transfer($fromWallet, $toWallet, $amount, $options);
    }

    /**
     * Transfer money between currencies/users (unit test compatible method)
     */
    public function transfer(
        float $amount,
        string $fromCurrency,
        $toUser,
        string $toCurrency,
        array $options = []
    ): Transfer {
        $fromWallet = $this->getWallet($fromCurrency);

        if (! $fromWallet) {
            throw new WalletNotFoundException("Wallet with currency {$fromCurrency} not found");
        }

        $toWallet = $toUser->getOrCreateWallet($toCurrency);

        return app(WalletManager::class)->transfer($fromWallet, $toWallet, $amount, $options);
    }

    /**
     * Get all transfers for this model
     */
    public function getAllTransfers()
    {
        return collect()
            ->merge($this->sentTransfers)
            ->merge($this->receivedTransfers)
            ->sortByDesc('created_at');
    }

    /**
     * Get total balance for a specific currency across all wallets
     */
    public function getTotalBalance(string $currency): float
    {
        return $this->getWalletsByCurrency($currency)
            ->sum(function ($wallet) {
                return $wallet->getTotalBalance();
            });
    }

    /**
     * Get available balance for a specific currency across all wallets
     */
    public function getAvailableBalance(string $currency): float
    {
        return $this->getWalletsByCurrency($currency)
            ->sum(function ($wallet) {
                return $wallet->getBalance(BalanceType::AVAILABLE);
            });
    }

    /**
     * Create a wallet with a specific name (forced creation)
     */
    public function forceCreateWallet(string $currency, ?string $name = null, array $attributes = []): Wallet
    {
        return $this->createWallet($currency, $name, $attributes);
    }

    /**
     * Delete a wallet and all its transactions
     */
    public function deleteWallet(string $currency, ?string $name = null): bool
    {
        $wallet = $this->getWallet($currency, $name);

        if (! $wallet) {
            return false;
        }

        // Check if wallet has balance
        if ($wallet->getTotalBalance() > 0) {
            throw new \Exception('Cannot delete wallet with remaining balance');
        }

        return $wallet->delete();
    }

    /**
     * Get wallet by slug
     */
    public function getWalletBySlug(string $slug): ?Wallet
    {
        return $this->wallets()->where('slug', $slug)->first();
    }

    /**
     * Get wallet or create if not exists
     */
    public function getWalletOrCreate(string $currency, ?string $name = null, array $attributes = []): Wallet
    {
        return $this->getOrCreateWallet($currency, $name, $attributes);
    }

    /**
     * Get wallet configuration from attributes
     */
    public function getWalletConfiguration(): array
    {
        if ($this->walletConfiguration === null) {
            $this->walletConfiguration = $this->loadWalletConfiguration();
        }

        return $this->walletConfiguration;
    }

    /**
     * Load wallet configuration from class attributes
     */
    protected function loadWalletConfiguration(): array
    {
        $reflection = new ReflectionClass($this);
        $attributes = $reflection->getAttributes(WalletConfiguration::class);

        if (empty($attributes)) {
            return [];
        }

        $config = [];
        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();
            $config = array_merge($config, $instance->toArray());
        }

        return $config;
    }

    /**
     * Auto-create wallet if configured
     */
    public function autoCreateWallet(): ?Wallet
    {
        $config = $this->getWalletConfiguration();

        if ($config['auto_create_wallet'] ?? false) {
            $currency = $config['default_currency'] ?? 'USD';
            $name = $config['wallet_name'] ?? null;
            $attributes = $config['metadata'] ?? [];

            return $this->getOrCreateWallet($currency, $name, $attributes);
        }

        return null;
    }

    /**
     * Get default wallet based on configuration
     */
    public function getDefaultWalletFromConfig(): ?Wallet
    {
        $config = $this->getWalletConfiguration();
        $currency = $config['default_currency'] ?? 'USD';
        $name = $config['wallet_name'] ?? null;

        return $this->getWallet($currency, $name);
    }

    /**
     * Create multiple wallets based on configuration
     */
    public function createWalletsFromConfig(): array
    {
        $config = $this->getWalletConfiguration();
        $currencies = $config['allowed_currencies'] ?? [];

        if (empty($currencies)) {
            return [];
        }

        $wallets = [];
        foreach ($currencies as $currency) {
            $wallets[$currency] = $this->getOrCreateWallet($currency);
        }

        return $wallets;
    }

    /**
     * Get bulk wallet manager
     */
    public function getBulkWalletManager(): BulkWalletManager
    {
        return app(BulkWalletManager::class);
    }

    /**
     * Execute bulk credit operations on wallets
     */
    public function bulkCreditWallets(array $operations): array
    {
        // Add holder information to operations
        foreach ($operations as &$operation) {
            if (! isset($operation['wallet_id'])) {
                $wallet = $this->getWallet($operation['currency'] ?? 'USD', $operation['name'] ?? null);
                if ($wallet) {
                    $operation['wallet_id'] = $wallet->id;
                }
            }
        }

        return $this->getBulkWalletManager()->bulkCredit($operations);
    }

    /**
     * Execute bulk debit operations on wallets
     */
    public function bulkDebitWallets(array $operations): array
    {
        // Add holder information to operations
        foreach ($operations as &$operation) {
            if (! isset($operation['wallet_id'])) {
                $wallet = $this->getWallet($operation['currency'] ?? 'USD', $operation['name'] ?? null);
                if ($wallet) {
                    $operation['wallet_id'] = $wallet->id;
                }
            }
        }

        return $this->getBulkWalletManager()->bulkDebit($operations);
    }

    /**
     * Execute bulk freeze operations on wallets
     */
    public function bulkFreezeWallets(array $operations): array
    {
        // Add holder information to operations
        foreach ($operations as &$operation) {
            if (! isset($operation['wallet_id'])) {
                $wallet = $this->getWallet($operation['currency'] ?? 'USD', $operation['name'] ?? null);
                if ($wallet) {
                    $operation['wallet_id'] = $wallet->id;
                }
            }
        }

        return $this->getBulkWalletManager()->bulkFreeze($operations);
    }

    /**
     * Execute bulk unfreeze operations on wallets
     */
    public function bulkUnfreezeWallets(array $operations): array
    {
        // Add holder information to operations
        foreach ($operations as &$operation) {
            if (! isset($operation['wallet_id'])) {
                $wallet = $this->getWallet($operation['currency'] ?? 'USD', $operation['name'] ?? null);
                if ($wallet) {
                    $operation['wallet_id'] = $wallet->id;
                }
            }
        }

        return $this->getBulkWalletManager()->bulkUnfreeze($operations);
    }

    /**
     * Get wallet configuration attribute value
     */
    public function getWalletConfigValue(string $key, $default = null)
    {
        $config = $this->getWalletConfiguration();

        return $config[$key] ?? $default;
    }

    /**
     * Check if wallet events are enabled
     */
    public function areWalletEventsEnabled(): bool
    {
        return $this->getWalletConfigValue('enable_events', true);
    }

    /**
     * Check if wallet audit log is enabled
     */
    public function isWalletAuditLogEnabled(): bool
    {
        return $this->getWalletConfigValue('enable_audit_log', false);
    }

    /**
     * Check if bulk operations are enabled
     */
    public function areBulkOperationsEnabled(): bool
    {
        return $this->getWalletConfigValue('enable_bulk_operations', true);
    }

    /**
     * Get allowed currencies for this model
     */
    public function getAllowedCurrencies(): array
    {
        return $this->getWalletConfigValue('allowed_currencies', []);
    }

    /**
     * Get wallet transaction limits
     */
    public function getWalletTransactionLimits(): array
    {
        return $this->getWalletConfigValue('transaction_limits', []);
    }

    /**
     * Get wallet limits
     */
    public function getWalletLimits(): array
    {
        return $this->getWalletConfigValue('wallet_limits', []);
    }

    /**
     * Get enabled balance types from configuration
     */
    public function getEnabledBalanceTypes(): array
    {
        return $this->getWalletConfigValue('balance_types', ['available', 'pending', 'frozen', 'trial']);
    }

    /**
     * Check if a specific balance type is enabled
     */
    public function isBalanceTypeEnabled(BalanceType|string $balanceType): bool
    {
        $enabledTypes = $this->getEnabledBalanceTypes();
        $typeValue = $balanceType instanceof BalanceType ? $balanceType->value : $balanceType;

        return in_array($typeValue, $enabledTypes);
    }

    /**
     * Get uniqueness settings
     */
    public function isUniquenessEnabled(): bool
    {
        return $this->getWalletConfigValue('uniqueness_enabled', true);
    }

    /**
     * Get uniqueness strategy
     */
    public function getUniquenessStrategy(): string
    {
        return $this->getWalletConfigValue('uniqueness_strategy', 'default');
    }

    /**
     * Get fee calculation settings
     */
    public function getFeeCalculationSettings(): array
    {
        return $this->getWalletConfigValue('fee_configuration', []);
    }

    /**
     * Get exchange rate configuration
     */
    public function getExchangeRateConfig(): array
    {
        return $this->getWalletConfigValue('exchange_rate_config', []);
    }

    /**
     * Get webhook settings
     */
    public function getWebhookSettings(): array
    {
        return $this->getWalletConfigValue('webhook_settings', []);
    }

    /**
     * Get notification settings
     */
    public function getNotificationSettings(): array
    {
        return $this->getWalletConfigValue('notification_settings', []);
    }

    /**
     * Get security settings
     */
    public function getSecuritySettings(): array
    {
        return $this->getWalletConfigValue('security_settings', []);
    }

    /**
     * Get maximum balance limit
     */
    public function getMaxBalanceLimit(): ?float
    {
        $limits = $this->getWalletLimits();

        return $limits['max_balance'] ?? null;
    }

    /**
     * Get minimum balance limit
     */
    public function getMinBalanceLimit(): float
    {
        $limits = $this->getWalletLimits();

        return $limits['min_balance'] ?? 0;
    }

    /**
     * Get maximum transaction amount
     */
    public function getMaxTransactionAmount(): ?float
    {
        $limits = $this->getWalletTransactionLimits();

        return $limits['max_amount'] ?? null;
    }

    /**
     * Get minimum transaction amount
     */
    public function getMinTransactionAmount(): float
    {
        $limits = $this->getWalletTransactionLimits();

        return $limits['min_amount'] ?? 0.01;
    }

    /**
     * Get daily transaction limit
     */
    public function getDailyTransactionLimit(): ?float
    {
        $limits = $this->getWalletTransactionLimits();

        return $limits['daily_limit'] ?? null;
    }

    /**
     * Validate transaction amount against limits
     */
    public function validateTransactionAmount(float $amount): bool
    {
        $minAmount = $this->getMinTransactionAmount();
        $maxAmount = $this->getMaxTransactionAmount();

        if ($amount < $minAmount) {
            return false;
        }

        if ($maxAmount && $amount > $maxAmount) {
            return false;
        }

        return true;
    }

    /**
     * Validate wallet balance against limits
     */
    public function validateWalletBalance(float $balance): bool
    {
        $minBalance = $this->getMinBalanceLimit();
        $maxBalance = $this->getMaxBalanceLimit();

        if ($balance < $minBalance) {
            return false;
        }

        if ($maxBalance && $balance > $maxBalance) {
            return false;
        }

        return true;
    }

    /**
     * Get freeze rules
     */
    public function getFreezeRules(): ?string
    {
        return $this->getWalletConfigValue('freeze_rules');
    }

    /**
     * Get metadata schema
     */
    public function getMetadataSchema(): array
    {
        return $this->getWalletConfigValue('metadata', []);
    }

    /**
     * Get default currency from configuration
     */
    public function getDefaultCurrency(): string
    {
        return $this->getWalletConfigValue('default_currency', 'USD');
    }

    /**
     * Get default wallet name from configuration
     */
    public function getDefaultWalletName(): ?string
    {
        return $this->getWalletConfigValue('wallet_name');
    }

    /**
     * Create wallet with configuration validation
     */
    public function createWalletWithValidation(string $currency, ?string $name = null, array $attributes = []): Wallet
    {
        // Validate currency is allowed
        $allowedCurrencies = $this->getAllowedCurrencies();
        if (! empty($allowedCurrencies) && ! in_array($currency, $allowedCurrencies)) {
            throw new \InvalidArgumentException("Currency {$currency} is not allowed for this model");
        }

        // Validate uniqueness if enabled
        if ($this->isUniquenessEnabled()) {
            $existing = $this->getWallet($currency, $name);
            if ($existing) {
                throw new \InvalidArgumentException('Wallet already exists for this currency and name combination');
            }
        }

        return $this->createWallet($currency, $name, $attributes);
    }

    /**
     * Get wallet configuration interface instance
     */
    public function getWalletConfigurationInterface(): WalletConfigurationInterface
    {
        return app(WalletConfigurationInterface::class);
    }

    /**
     * Sync configuration with global settings
     */
    public function syncWithGlobalConfiguration(): void
    {
        $globalConfig = $this->getWalletConfigurationInterface();
        $localConfig = $this->getWalletConfiguration();

        // Merge configurations with local taking precedence
        $this->walletConfiguration = array_merge($globalConfig->all(), $localConfig);
    }
}
