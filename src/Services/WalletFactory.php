<?php

namespace HWallet\LaravelMultiWallet\Services;

use HWallet\LaravelMultiWallet\Contracts\WalletConfigurationInterface;
use HWallet\LaravelMultiWallet\Models\Wallet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WalletFactory
{
    protected WalletConfigurationInterface $config;

    public function __construct(WalletConfigurationInterface $config)
    {
        $this->config = $config;
    }

    /**
     * Create a new wallet instance
     */
    public function create(
        Model $holder,
        string $currency,
        ?string $name = null,
        array $attributes = []
    ): Wallet {
        $currency = strtoupper($currency);

        $walletData = array_merge([
            'holder_type' => get_class($holder),
            'holder_id' => $holder->getKey(),
            'currency' => $currency,
            'name' => $name,
            'description' => null,
            'meta' => [],
            'balance_pending' => 0,
            'balance_available' => 0,
            'balance_frozen' => 0,
            'balance_trial' => 0,
        ], $attributes);

        // Generate unique slug if not provided
        if (empty($walletData['slug'])) {
            $walletData['slug'] = $this->generateUniqueSlug($walletData);
        }

        return new Wallet($walletData);
    }

    /**
     * Create and save a new wallet
     */
    public function createAndSave(
        Model $holder,
        string $currency,
        ?string $name = null,
        array $attributes = []
    ): Wallet {
        $wallet = $this->create($holder, $currency, $name, $attributes);
        $wallet->save();

        return $wallet;
    }

    /**
     * Create a wallet with default configuration
     */
    public function createDefault(Model $holder, ?string $currency = null): Wallet
    {
        $currency = $currency ?? $this->config->getDefaultCurrency();

        return $this->createAndSave($holder, $currency, null, [
            'description' => 'Default wallet',
            'meta' => ['created_by' => 'factory', 'is_default' => true],
        ]);
    }

    /**
     * Create multiple wallets for different currencies
     */
    public function createMultiple(Model $holder, array $currencies): array
    {
        $wallets = [];

        foreach ($currencies as $currency => $config) {
            if (is_numeric($currency)) {
                // Simple array of currencies
                $currency = $config;
                $config = [];
            }

            $name = $config['name'] ?? null;
            $attributes = $config['attributes'] ?? [];

            $wallets[$currency] = $this->createAndSave($holder, $currency, $name, $attributes);
        }

        return $wallets;
    }

    /**
     * Create a wallet with initial balance
     */
    public function createWithBalance(
        Model $holder,
        string $currency,
        float $initialBalance,
        string $balanceType = 'available',
        ?string $name = null
    ): Wallet {
        $wallet = $this->createAndSave($holder, $currency, $name);
        $wallet->credit($initialBalance, $balanceType, [
            'description' => 'Initial balance',
            'created_by' => 'factory',
        ]);

        return $wallet;
    }

    /**
     * Generate a unique slug for the wallet
     */
    protected function generateUniqueSlug(array $walletData): string
    {
        $baseSlug = Str::slug($walletData['name'] ?: $walletData['currency'].'-wallet');
        $slug = $baseSlug;
        $counter = 1;

        while (Wallet::where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$counter++;
        }

        return $slug;
    }

    /**
     * Validate wallet data before creation
     */
    protected function validateWalletData(array $data): void
    {
        $required = ['holder_type', 'holder_id', 'currency'];

        foreach ($required as $field) {
            if (! isset($data[$field]) || empty($data[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }

        // Validate currency
        $exchangeProvider = $this->config->getExchangeRateProvider();
        if (! $exchangeProvider->supportsCurrency($data['currency'])) {
            throw new \InvalidArgumentException("Unsupported currency: {$data['currency']}");
        }
    }
}
