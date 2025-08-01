<?php

namespace HWallet\LaravelMultiWallet\Facades;

use HWallet\LaravelMultiWallet\Helpers\WalletHelpers;
use HWallet\LaravelMultiWallet\Models\Transfer;
use HWallet\LaravelMultiWallet\Models\Wallet;
use HWallet\LaravelMultiWallet\Services\BulkWalletManager;
use HWallet\LaravelMultiWallet\Services\Validators\WalletValidator;
use HWallet\LaravelMultiWallet\Services\WalletFactory;
use HWallet\LaravelMultiWallet\Services\WalletManager;
use HWallet\LaravelMultiWallet\Types\WalletTypes;
use HWallet\LaravelMultiWallet\Utils\WalletUtils;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;

/**
 * @see \HWallet\LaravelMultiWallet\Services\WalletManager
 *
 * @method static Wallet create(Model $holder, string $currency, ?string $name = null, array $attributes = [])
 * @method static Wallet getOrCreate(Model $holder, string $currency, ?string $name = null, array $attributes = [])
 * @method static Wallet getBySlug(string $slug)
 * @method static Transfer transfer(Wallet $fromWallet, Wallet $toWallet, float $amount, array $options = [])
 * @method static Transfer transferWithFee(Wallet $fromWallet, Wallet $toWallet, float $amount, float $fee, string $description = '')
 * @method static array batchTransfer(Wallet $fromWallet, array $recipients, array $options = [])
 * @method static float calculateFee(float $amount, array $feeConfig = [])
 * @method static \Illuminate\Database\Eloquent\Builder getTransactionHistory(Wallet $wallet, array $filters = [])
 * @method static array getBalanceSummary(Model $holder, ?string $currency = null)
 * @method static bool validateTransactionLimits(float $amount)
 * @method static \HWallet\LaravelMultiWallet\Contracts\WalletConfigurationInterface getConfiguration()
 * @method static \HWallet\LaravelMultiWallet\Contracts\ExchangeRateProviderInterface getExchangeRateProvider()
 * @method static WalletManager getWalletManager()
 * @method static BulkWalletManager getBulkWalletManager()
 * @method static WalletFactory getWalletFactory()
 * @method static WalletValidator getValidator()
 * @method static WalletUtils getUtils()
 * @method static WalletTypes getTypes()
 * @method static WalletHelpers getHelpers()
 * @method static mixed createWallet(Model $holder, string $currency, ?string $name = null, array $attributes = [])
 * @method static mixed getBalanceSummary(Model $user)
 * @method static mixed validateWallet(Wallet $wallet)
 * @method static mixed reconcileWallet(Wallet $wallet)
 * @method static mixed debugWallet(Wallet $wallet)
 * @method static mixed generateWalletReport(Wallet $wallet, string $reportType = 'summary')
 * @method static mixed checkWalletHealth(Wallet $wallet)
 * @method static mixed exportWalletData(Wallet $wallet)
 * @method static mixed calculateWalletMetrics(Wallet $wallet, int $days = 30)
 * @method static mixed getWalletAuditTrail(Wallet $wallet, int $limit = 50)
 * @method static mixed formatAmount(float $amount, string $currency = 'USD', int $decimals = 2)
 * @method static mixed isCurrencySupported(string $currency)
 * @method static mixed validateAmount(float $amount, ?float $minAmount = null, ?float $maxAmount = null)
 * @method static mixed calculateTransferFee(float $amount, float $feePercentage = 0, float $fixedFee = 0)
 * @method static mixed roundAmount(float $amount, int $decimals = 2)
 * @method static mixed createAmount(float $amount)
 * @method static mixed createCurrency(string $currency)
 * @method static mixed createWalletId(int $id)
 * @method static mixed createTransactionId(int $id)
 * @method static mixed createTransferId(int $id)
 * @method static mixed createWalletMetadata(array $metadata)
 * @method static mixed createTransactionMetadata(array $metadata)
 * @method static mixed createBalanceSummary(array $balances)
 * @method static mixed createWalletConfiguration(array $config)
 * @method static mixed validateWalletCreation(Model $holder, string $currency, ?string $name = null, array $attributes = [])
 * @method static mixed validateTransactionCreation(Wallet $wallet, float $amount, string $balanceType, array $meta = [])
 * @method static mixed validateTransferCreation(Wallet $fromWallet, Wallet $toWallet, float $amount, array $options = [])
 * @method static mixed validateBulkOperations(array $operations, string $operationType)
 * @method static mixed validateWalletConfiguration(array $config)
 */
class LaravelMultiWallet extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'laravel-multi-wallet';
    }

    /**
     * Get the wallet manager instance
     */
    public static function getWalletManager(): WalletManager
    {
        return app(WalletManager::class);
    }

    /**
     * Get the bulk wallet manager instance
     */
    public static function getBulkWalletManager(): BulkWalletManager
    {
        return app(BulkWalletManager::class);
    }

    /**
     * Get the wallet factory instance
     */
    public static function getWalletFactory(): WalletFactory
    {
        return app(WalletFactory::class);
    }

    /**
     * Get the validator instance
     */
    public static function getValidator(): WalletValidator
    {
        return app(WalletValidator::class);
    }

    /**
     * Get the utils instance
     */
    public static function getUtils(): WalletUtils
    {
        return app(WalletUtils::class);
    }

    /**
     * Get the types instance
     */
    public static function getTypes(): WalletTypes
    {
        return app(WalletTypes::class);
    }

    /**
     * Get the helpers instance
     */
    public static function getHelpers(): WalletHelpers
    {
        return app(WalletHelpers::class);
    }

    /**
     * Create a wallet using the wallet manager
     */
    public static function createWallet($holder, string $currency, ?string $name = null, array $attributes = [])
    {
        return static::getWalletManager()->create($holder, $currency, $name, $attributes);
    }

    /**
     * Transfer between wallets using the wallet manager
     */
    public static function transfer($fromWallet, $toWallet, float $amount, array $options = [])
    {
        return static::getWalletManager()->transfer($fromWallet, $toWallet, $amount, $options);
    }

    /**
     * Get balance summary using helpers
     */
    public static function getBalanceSummary($user)
    {
        return WalletHelpers::getUserWalletSummary($user);
    }

    /**
     * Validate wallet using utils
     */
    public static function validateWallet($wallet)
    {
        return WalletUtils::validateWalletIntegrity($wallet);
    }

    /**
     * Reconcile wallet using utils
     */
    public static function reconcileWallet($wallet)
    {
        return WalletUtils::reconcileWallet($wallet);
    }

    /**
     * Debug wallet using utils
     */
    public static function debugWallet($wallet)
    {
        return WalletUtils::debugWallet($wallet);
    }

    /**
     * Generate wallet report using utils
     */
    public static function generateWalletReport($wallet, string $reportType = 'summary')
    {
        return WalletUtils::generateWalletReport($wallet, $reportType);
    }

    /**
     * Check wallet health using utils
     */
    public static function checkWalletHealth($wallet)
    {
        return WalletUtils::checkWalletHealth($wallet);
    }

    /**
     * Export wallet data using utils
     */
    public static function exportWalletData($wallet)
    {
        return WalletUtils::exportWalletData($wallet);
    }

    /**
     * Calculate wallet metrics using utils
     */
    public static function calculateWalletMetrics($wallet, int $days = 30)
    {
        return WalletUtils::calculateWalletMetrics($wallet, $days);
    }

    /**
     * Get wallet audit trail using utils
     */
    public static function getWalletAuditTrail($wallet, int $limit = 50)
    {
        return WalletUtils::getWalletAuditTrail($wallet, $limit);
    }

    /**
     * Format amount using helpers
     */
    public static function formatAmount(float $amount, string $currency = 'USD', int $decimals = 2)
    {
        return WalletHelpers::formatAmountStatic($amount, $currency, $decimals);
    }

    /**
     * Check if currency is supported using helpers
     */
    public static function isCurrencySupported(string $currency)
    {
        return WalletHelpers::isCurrencySupported($currency);
    }

    /**
     * Validate amount using helpers
     */
    public static function validateAmount(float $amount, ?float $minAmount = null, ?float $maxAmount = null)
    {
        return WalletHelpers::validateTransferAmount($amount, $minAmount, $maxAmount);
    }

    /**
     * Calculate transfer fee using helpers
     */
    public static function calculateTransferFee(float $amount, float $feePercentage = 0, float $fixedFee = 0)
    {
        return WalletHelpers::calculateTransferFee($amount, $feePercentage, $fixedFee);
    }

    /**
     * Round amount using helpers
     */
    public static function roundAmount(float $amount, int $decimals = 2)
    {
        return WalletHelpers::roundAmount($amount, $decimals);
    }

    /**
     * Create amount using types
     */
    public static function createAmount(float $amount)
    {
        return static::getTypes()->createAmount($amount);
    }

    /**
     * Create currency using types
     */
    public static function createCurrency(string $currency)
    {
        return static::getTypes()->createCurrency($currency);
    }

    /**
     * Create wallet ID using types
     */
    public static function createWalletId(int $id)
    {
        return static::getTypes()->createWalletId($id);
    }

    /**
     * Create transaction ID using types
     */
    public static function createTransactionId(int $id)
    {
        return static::getTypes()->createTransactionId($id);
    }

    /**
     * Create transfer ID using types
     */
    public static function createTransferId(int $id)
    {
        return static::getTypes()->createTransferId($id);
    }

    /**
     * Create wallet metadata using types
     */
    public static function createWalletMetadata(array $metadata)
    {
        return static::getTypes()->createWalletMetadata($metadata);
    }

    /**
     * Create transaction metadata using types
     */
    public static function createTransactionMetadata(array $metadata)
    {
        return static::getTypes()->createTransactionMetadata($metadata);
    }

    /**
     * Create balance summary using types
     */
    public static function createBalanceSummary(array $balances)
    {
        return static::getTypes()->createBalanceSummary($balances);
    }

    /**
     * Create wallet configuration using types
     */
    public static function createWalletConfiguration(array $config)
    {
        return static::getTypes()->createWalletConfiguration($config);
    }

    /**
     * Validate wallet creation using validator
     */
    public static function validateWalletCreation($holder, string $currency, ?string $name = null, array $attributes = [])
    {
        return static::getValidator()->validateWalletCreation($holder, $currency, $name, $attributes);
    }

    /**
     * Validate transaction creation using validator
     */
    public static function validateTransactionCreation($wallet, float $amount, string $balanceType, array $meta = [])
    {
        return static::getValidator()->validateTransactionCreation($wallet, $amount, $balanceType, $meta);
    }

    /**
     * Validate transfer creation using validator
     */
    public static function validateTransferCreation($fromWallet, $toWallet, float $amount, array $options = [])
    {
        return static::getValidator()->validateTransferCreation($fromWallet, $toWallet, $amount, $options);
    }

    /**
     * Validate bulk operations using validator
     */
    public static function validateBulkOperations(array $operations, string $operationType)
    {
        return static::getValidator()->validateBulkOperations($operations, $operationType);
    }

    /**
     * Validate wallet configuration using validator
     */
    public static function validateWalletConfiguration(array $config)
    {
        return static::getValidator()->validateWalletConfiguration($config);
    }

    /**
     * Get the integration service instance
     */
    public static function getIntegrationService()
    {
        return app(\HWallet\LaravelMultiWallet\Services\WalletIntegrationService::class);
    }

    /**
     * Create wallet with full validation and type safety
     */
    public static function createWalletSafe($holder, string $currency, ?string $name = null, array $metadata = [], array $configuration = [])
    {
        return static::getIntegrationService()->createWallet($holder, $currency, $name, $metadata, $configuration);
    }

    /**
     * Perform transaction with validation and type checking
     */
    public static function performTransactionSafe($wallet, string $type, float $amount, string $balanceType = 'available', array $metadata = [])
    {
        return static::getIntegrationService()->performTransaction($wallet, $type, $amount, $balanceType, $metadata);
    }

    /**
     * Get comprehensive wallet dashboard
     */
    public static function getWalletDashboard($holder)
    {
        return static::getIntegrationService()->getWalletDashboard($holder);
    }

    /**
     * Get wallet analytics
     */
    public static function getWalletAnalytics($wallet, array $options = [])
    {
        return static::getIntegrationService()->getWalletAnalytics($wallet, $options);
    }

    /**
     * Perform bulk operations with progress tracking
     */
    public static function performBulkOperationSafe(array $wallets, string $operation, array $parameters = [], ?callable $progressCallback = null)
    {
        return static::getIntegrationService()->performBulkOperation($wallets, $operation, $parameters, $progressCallback);
    }

    /**
     * Generate comprehensive reports
     */
    public static function generateReport($holder, string $type, array $options = [])
    {
        return static::getIntegrationService()->generateReport($holder, $type, $options);
    }

    /**
     * Search wallets with advanced criteria
     */
    public static function searchWalletsSafe(array $criteria, array $options = [])
    {
        return static::getIntegrationService()->searchWallets($criteria, $options);
    }
}
