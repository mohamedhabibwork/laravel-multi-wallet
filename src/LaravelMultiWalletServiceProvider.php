<?php

namespace HWallet\LaravelMultiWallet;

use HWallet\LaravelMultiWallet\Contracts\ExchangeRateProviderInterface;
use HWallet\LaravelMultiWallet\Contracts\WalletConfigurationInterface;
use HWallet\LaravelMultiWallet\Models\Transaction;
use HWallet\LaravelMultiWallet\Models\Transfer;
use HWallet\LaravelMultiWallet\Models\Wallet;
use HWallet\LaravelMultiWallet\Observers\TransactionObserver;
use HWallet\LaravelMultiWallet\Observers\TransferObserver;
use HWallet\LaravelMultiWallet\Observers\WalletObserver;
use HWallet\LaravelMultiWallet\Repositories\EloquentWalletRepository;
use HWallet\LaravelMultiWallet\Repositories\WalletRepositoryInterface;
use HWallet\LaravelMultiWallet\Services\DefaultExchangeRateProvider;
use HWallet\LaravelMultiWallet\Services\WalletConfiguration;
use HWallet\LaravelMultiWallet\Services\WalletFactory;
use HWallet\LaravelMultiWallet\Services\WalletManager;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelMultiWalletServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-multi-wallet')
            ->hasConfigFile('multi-wallet')
            ->hasViews()
            ->hasMigration('create_multi_wallet_table')
            ->hasCommands([]);
    }

    public function packageBooted(): void
    {
        // Register model observers
        Wallet::observe(WalletObserver::class);
        Transaction::observe(TransactionObserver::class);
        Transfer::observe(TransferObserver::class);
    }



    public function packageRegistered(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/multi-wallet.php', 'multi-wallet');

        // Register contracts
        $this->app->bind(\HWallet\LaravelMultiWallet\Contracts\WalletInterface::class, Wallet::class);
        $this->app->bind(WalletConfigurationInterface::class, WalletConfiguration::class);
        $this->app->bind(ExchangeRateProviderInterface::class, DefaultExchangeRateProvider::class);
        $this->app->bind(\HWallet\LaravelMultiWallet\Contracts\BulkWalletOperationInterface::class, \HWallet\LaravelMultiWallet\Services\BulkWalletManager::class);
        $this->app->bind(WalletRepositoryInterface::class, EloquentWalletRepository::class);
        $this->app->bind(\HWallet\LaravelMultiWallet\Services\FeeCalculators\FeeCalculatorInterface::class, \HWallet\LaravelMultiWallet\Services\FeeCalculators\DefaultFeeCalculator::class);
        $this->app->bind(\HWallet\LaravelMultiWallet\Services\Strategies\UniquenessStrategyInterface::class, \HWallet\LaravelMultiWallet\Services\Strategies\DefaultUniquenessStrategy::class);
        $this->app->bind(\HWallet\LaravelMultiWallet\Contracts\ValidatorInterface::class, \HWallet\LaravelMultiWallet\Services\Validators\WalletValidator::class);

        // Register services
        $this->app->singleton(WalletManager::class);
        $this->app->singleton(\HWallet\LaravelMultiWallet\Services\BulkWalletManager::class);
        $this->app->singleton(WalletFactory::class);
        $this->app->singleton(\HWallet\LaravelMultiWallet\Services\WalletIntegrationService::class);

        // Register helpers and utilities
        $this->registerHelpers();
        $this->registerUtilities();
        $this->registerTypes();

        // Register the configuration interface and implementation
        $this->app->singleton(WalletConfigurationInterface::class, function ($app) {
            $config = $app['config']->get('multi-wallet', []);

            // Load exchange rates from config
            if (isset($config['exchange_rates'])) {
                $config['exchange_rates'] = $config['exchange_rates'];
            }

            // Load supported currencies from config
            if (isset($config['supported_currencies'])) {
                $config['supported_currencies'] = $config['supported_currencies'];
            }

            return new WalletConfiguration($config);
        });

        // Register the default exchange rate provider
        $this->app->singleton(ExchangeRateProviderInterface::class, function ($app) {
            $config = $app->make(WalletConfigurationInterface::class);
            $supportedCurrencies = $config->get('supported_currencies', []);
            $exchangeRates = $config->get('exchange_rates', []);

            return new DefaultExchangeRateProvider($supportedCurrencies, $exchangeRates);
        });

        // Register repository interface and implementation
        $this->app->singleton(WalletRepositoryInterface::class, EloquentWalletRepository::class);

        // Register the wallet factory
        $this->app->singleton(WalletFactory::class, function ($app) {
            return new WalletFactory($app[WalletConfigurationInterface::class]);
        });

        // Register the wallet manager service
        $this->app->singleton(WalletManager::class, function ($app) {
            return new WalletManager($app[WalletConfigurationInterface::class]);
        });

        // Register the bulk wallet manager service
        $this->app->singleton(\HWallet\LaravelMultiWallet\Services\BulkWalletManager::class, function ($app) {
            return new \HWallet\LaravelMultiWallet\Services\BulkWalletManager(
                $app[WalletConfigurationInterface::class],
                $app[WalletManager::class]
            );
        });

        // Register aliases for easier access
        $this->app->alias(WalletManager::class, 'wallet-manager');
        $this->app->alias(\HWallet\LaravelMultiWallet\Services\BulkWalletManager::class, 'bulk-wallet-manager');
        $this->app->alias(WalletConfigurationInterface::class, 'wallet-config');
        $this->app->alias(ExchangeRateProviderInterface::class, 'exchange-rate-provider');
        $this->app->alias(WalletRepositoryInterface::class, 'wallet-repository');
        $this->app->alias(WalletFactory::class, 'wallet-factory');
    }

    /**
     * Register helper functions
     */
    protected function registerHelpers(): void
    {
        // Register helper service as singleton
        $this->app->singleton(\HWallet\LaravelMultiWallet\Helpers\WalletHelpers::class);
        
        // Load global helper functions file
        $this->loadHelperFunctions();
    }

    /**
     * Load global helper functions
     */
    protected function loadHelperFunctions(): void
    {
        $helperFile = __DIR__ . '/helpers.php';
        if (file_exists($helperFile)) {
            require_once $helperFile;
        }
    }

    /**
     * Register utility services
     */
    protected function registerUtilities(): void
    {
        // Register utility classes as singletons
        $this->app->singleton(\HWallet\LaravelMultiWallet\Utils\WalletUtils::class);
    }

    /**
     * Register type services
     */
    protected function registerTypes(): void
    {
        // Register type factory as singleton
        $this->app->singleton(\HWallet\LaravelMultiWallet\Types\WalletTypes::class);
    }
}
