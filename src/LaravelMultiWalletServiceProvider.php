<?php

namespace HWallet\LaravelMultiWallet;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use HWallet\LaravelMultiWallet\Commands\LaravelMultiWalletCommand;

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
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_laravel_multi_wallet_table')
            ->hasCommand(LaravelMultiWalletCommand::class);
    }
}
