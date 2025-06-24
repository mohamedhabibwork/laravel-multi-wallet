<?php

namespace HWallet\LaravelMultiWallet\Providers;

use HWallet\LaravelMultiWallet\Events\ExchangeRateUpdated;
use HWallet\LaravelMultiWallet\Events\SuspiciousActivityDetected;
use HWallet\LaravelMultiWallet\Events\TransactionConfirmed;
use HWallet\LaravelMultiWallet\Events\TransactionCreated;
use HWallet\LaravelMultiWallet\Events\TransactionFailed;
use HWallet\LaravelMultiWallet\Events\TransactionReversed;
use HWallet\LaravelMultiWallet\Events\TransferCompleted;
use HWallet\LaravelMultiWallet\Events\TransferFailed;
use HWallet\LaravelMultiWallet\Events\TransferInitiated;
use HWallet\LaravelMultiWallet\Events\TransferPending;
use HWallet\LaravelMultiWallet\Events\TransferRejected;
use HWallet\LaravelMultiWallet\Events\WalletBalanceChanged;
use HWallet\LaravelMultiWallet\Events\WalletCreated;
use HWallet\LaravelMultiWallet\Events\WalletDeleted;
use HWallet\LaravelMultiWallet\Events\WalletFrozen;
use HWallet\LaravelMultiWallet\Events\WalletLimitExceeded;
use HWallet\LaravelMultiWallet\Events\WalletReconciled;
use HWallet\LaravelMultiWallet\Events\WalletUnfrozen;
use HWallet\LaravelMultiWallet\Events\WalletUpdated;
use HWallet\LaravelMultiWallet\Listeners\LogWalletActivity;
use HWallet\LaravelMultiWallet\Listeners\SendWebhookNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, string>>
     */
    protected $listen = [
        // Wallet Events
        WalletCreated::class => [
            LogWalletActivity::class.'@handleWalletCreated',
        ],
        WalletUpdated::class => [
            LogWalletActivity::class.'@handleWalletUpdated',
        ],
        WalletDeleted::class => [
            LogWalletActivity::class.'@handleWalletDeleted',
        ],
        WalletBalanceChanged::class => [
            LogWalletActivity::class.'@handleWalletBalanceChanged',
            SendWebhookNotification::class.'@handleWalletBalanceChanged',
        ],
        WalletFrozen::class => [
            LogWalletActivity::class.'@handleWalletFrozen',
        ],
        WalletUnfrozen::class => [
            LogWalletActivity::class.'@handleWalletUnfrozen',
        ],
        WalletLimitExceeded::class => [
            LogWalletActivity::class.'@handleWalletLimitExceeded',
        ],
        WalletReconciled::class => [
            LogWalletActivity::class.'@handleWalletReconciled',
        ],

        // Transaction Events
        TransactionCreated::class => [
            LogWalletActivity::class.'@handleTransactionCreated',
        ],
        TransactionConfirmed::class => [
            LogWalletActivity::class.'@handleTransactionConfirmed',
        ],
        TransactionFailed::class => [
            LogWalletActivity::class.'@handleTransactionFailed',
        ],
        TransactionReversed::class => [
            LogWalletActivity::class.'@handleTransactionReversed',
        ],

        // Transfer Events
        TransferInitiated::class => [
            LogWalletActivity::class.'@handleTransferInitiated',
        ],
        TransferCompleted::class => [
            LogWalletActivity::class.'@handleTransferCompleted',
            SendWebhookNotification::class.'@handleTransferCompleted',
        ],
        TransferFailed::class => [
            LogWalletActivity::class.'@handleTransferFailed',
        ],
        TransferRejected::class => [
            LogWalletActivity::class.'@handleTransferRejected',
        ],
        TransferPending::class => [
            LogWalletActivity::class.'@handleTransferPending',
        ],

        // Security Events
        SuspiciousActivityDetected::class => [
            LogWalletActivity::class.'@handleSuspiciousActivityDetected',
            SendWebhookNotification::class.'@handleSuspiciousActivityDetected',
        ],

        // System Events
        ExchangeRateUpdated::class => [
            LogWalletActivity::class.'@handleExchangeRateUpdated',
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
