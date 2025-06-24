<?php

namespace HWallet\LaravelMultiWallet\Facades;

use HWallet\LaravelMultiWallet\Models\Transfer;
use HWallet\LaravelMultiWallet\Models\Wallet;
use HWallet\LaravelMultiWallet\Services\WalletManager;
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
 */
class LaravelMultiWallet extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return WalletManager::class;
    }
}
