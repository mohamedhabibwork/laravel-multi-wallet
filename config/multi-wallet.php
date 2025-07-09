<?php

// config for HWallet/LaravelMultiWallet
return [
    /*
    |--------------------------------------------------------------------------
    | Default Currency
    |--------------------------------------------------------------------------
    |
    | This value is the default currency used when creating wallets without
    | explicitly specifying a currency. It should be a valid currency code.
    |
    */
    'default_currency' => env('WALLET_DEFAULT_CURRENCY', 'USD'),

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | Configure the Eloquent models used by the package. You can extend these
    | models or replace them with your own implementations.
    |
    */
    'models' => [
        'wallet' => \HWallet\LaravelMultiWallet\Models\Wallet::class,
        'transaction' => \HWallet\LaravelMultiWallet\Models\Transaction::class,
        'transfer' => \HWallet\LaravelMultiWallet\Models\Transfer::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    |
    | Configure the table names used by the package. You can customize these
    | to match your application's naming conventions.
    |
    */
    'table_names' => [
        'wallets' => 'wallets',
        'transactions' => 'transactions',
        'transfers' => 'transfers',
    ],

    /*
    |--------------------------------------------------------------------------
    | Wallet Limits
    |--------------------------------------------------------------------------
    |
    | Configure limits for wallet balances. Set to null to disable limits.
    |
    */
    'wallet_limits' => [
        'max_balance' => env('WALLET_MAX_BALANCE', null),
        'min_balance' => env('WALLET_MIN_BALANCE', 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Transaction Limits
    |--------------------------------------------------------------------------
    |
    | Configure limits for individual transactions. Set to null to disable.
    |
    */
    'transaction_limits' => [
        'max_amount' => env('WALLET_MAX_TRANSACTION', null),
        'min_amount' => env('WALLET_MIN_TRANSACTION', 0.01),
        'daily_limit' => env('WALLET_DAILY_LIMIT', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Enabled Balance Types
    |--------------------------------------------------------------------------
    |
    | Configure which balance types are enabled in your application.
    | Available options: pending, available, frozen, trial
    |
    */
    'balance_types' => [
        'pending',
        'available',
        'frozen',
        'trial',
    ],

    /*
    |--------------------------------------------------------------------------
    | Wallet Uniqueness
    |--------------------------------------------------------------------------
    |
    | Configure wallet uniqueness constraints. When enabled, prevents
    | creation of duplicate wallets for the same holder, currency, and name.
    |
    */
    'uniqueness_enabled' => env('WALLET_UNIQUENESS_ENABLED', true),
    'uniqueness_strategy' => env('WALLET_UNIQUENESS_STRATEGY', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Fee Calculation
    |--------------------------------------------------------------------------
    |
    | Configure default fee calculation settings for transfers.
    |
    */
    'fee_calculation' => [
        'default_fee' => env('WALLET_DEFAULT_FEE', 0),
        'percentage_based' => env('WALLET_FEE_PERCENTAGE_BASED', false),
        'fee_percentage' => env('WALLET_FEE_PERCENTAGE', 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Exchange Rate Provider
    |--------------------------------------------------------------------------
    |
    | Configure the exchange rate provider class. This class should implement
    | the ExchangeRateProviderInterface.
    |
    */
    'exchange_rate_provider' => \HWallet\LaravelMultiWallet\Services\DefaultExchangeRateProvider::class,

    /*
    |--------------------------------------------------------------------------
    | Supported Currencies
    |--------------------------------------------------------------------------
    |
    | List of currencies supported by the default exchange rate provider.
    | Add or remove currencies as needed for your application.
    |
    */
    'supported_currencies' => [
        'USD', 'EUR', 'GBP', 'JPY', 'CAD', 'AUD', 'CHF', 'CNY', 'SEK', 'NZD',
        'MXN', 'SGD', 'HKD', 'NOK', 'ZAR', 'TRY', 'RUB', 'INR', 'BRL', 'KRW',
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Exchange Rates
    |--------------------------------------------------------------------------
    |
    | Define custom exchange rates in the format 'FROM_TO' => rate.
    | These rates will override the default 1:1 rate.
    |
    */
    'exchange_rates' => [
        // Example: 'USD_EUR' => 0.85,
        // Example: 'EUR_USD' => 1.18,
    ],

    /*
    |--------------------------------------------------------------------------
    | Metadata Schema
    |--------------------------------------------------------------------------
    |
    | Define validation rules for wallet metadata. This allows you to enforce
    | structure on the metadata stored with wallets.
    |
    */
    'metadata_schema' => [
        // Example validation rules for metadata
        // 'department' => 'string|max:100',
        // 'cost_center' => 'numeric',
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Logging
    |--------------------------------------------------------------------------
    |
    | Enable or disable audit logging for wallet operations. When enabled,
    | all wallet operations will be logged for audit purposes.
    |
    */
    'audit_logging_enabled' => env('WALLET_AUDIT_LOGGING', true),

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure caching for wallet operations to improve performance.
    |
    */
    'cache' => [
        'enabled' => env('WALLET_CACHE_ENABLED', false),
        'ttl' => env('WALLET_CACHE_TTL', 3600), // 1 hour
        'prefix' => env('WALLET_CACHE_PREFIX', 'wallet'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Events
    |--------------------------------------------------------------------------
    |
    | Enable or disable event firing for wallet operations. When enabled,
    | events will be fired for wallet creation, transactions, transfers, etc.
    |
    */
    'events' => [
        'enabled' => env('WALLET_EVENTS_ENABLED', true),
        'listeners' => [
            // LogWalletActivity - Logs all wallet operations for audit trail
            \HWallet\LaravelMultiWallet\Listeners\LogWalletActivity::class,
            // SendWebhookNotification - Sends webhook notifications for wallet events
            \HWallet\LaravelMultiWallet\Listeners\SendWebhookNotification::class,
        ],
        'available_events' => [
            // Bulk Operation Events
            'bulk_operation_started' => \HWallet\LaravelMultiWallet\Events\BulkOperationStarted::class,
            'bulk_operation_completed' => \HWallet\LaravelMultiWallet\Events\BulkOperationCompleted::class,
            'bulk_operation_failed' => \HWallet\LaravelMultiWallet\Events\BulkOperationFailed::class,

            // Exchange Rate Events
            'exchange_rate_updated' => \HWallet\LaravelMultiWallet\Events\ExchangeRateUpdated::class,

            // Security Events
            'suspicious_activity_detected' => \HWallet\LaravelMultiWallet\Events\SuspiciousActivityDetected::class,

            // Transaction Events
            'transaction_created' => \HWallet\LaravelMultiWallet\Events\TransactionCreated::class,
            'transaction_confirmed' => \HWallet\LaravelMultiWallet\Events\TransactionConfirmed::class,
            'transaction_failed' => \HWallet\LaravelMultiWallet\Events\TransactionFailed::class,
            'transaction_reversed' => \HWallet\LaravelMultiWallet\Events\TransactionReversed::class,

            // Transfer Events
            'transfer_initiated' => \HWallet\LaravelMultiWallet\Events\TransferInitiated::class,
            'transfer_pending' => \HWallet\LaravelMultiWallet\Events\TransferPending::class,
            'transfer_completed' => \HWallet\LaravelMultiWallet\Events\TransferCompleted::class,
            'transfer_failed' => \HWallet\LaravelMultiWallet\Events\TransferFailed::class,
            'transfer_rejected' => \HWallet\LaravelMultiWallet\Events\TransferRejected::class,

            // Wallet Events
            'wallet_created' => \HWallet\LaravelMultiWallet\Events\WalletCreated::class,
            'wallet_updated' => \HWallet\LaravelMultiWallet\Events\WalletUpdated::class,
            'wallet_deleted' => \HWallet\LaravelMultiWallet\Events\WalletDeleted::class,
            'wallet_balance_changed' => \HWallet\LaravelMultiWallet\Events\WalletBalanceChanged::class,
            'wallet_frozen' => \HWallet\LaravelMultiWallet\Events\WalletFrozen::class,
            'wallet_unfrozen' => \HWallet\LaravelMultiWallet\Events\WalletUnfrozen::class,
            'wallet_limit_exceeded' => \HWallet\LaravelMultiWallet\Events\WalletLimitExceeded::class,
            'wallet_reconciled' => \HWallet\LaravelMultiWallet\Events\WalletReconciled::class,
            'wallet_configuration_changed' => \HWallet\LaravelMultiWallet\Events\WalletConfigurationChanged::class,

            // Wallet Operation Events
            'wallet_operation_started' => \HWallet\LaravelMultiWallet\Events\WalletOperationStarted::class,
            'wallet_operation_completed' => \HWallet\LaravelMultiWallet\Events\WalletOperationCompleted::class,
            'wallet_operation_failed' => \HWallet\LaravelMultiWallet\Events\WalletOperationFailed::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Wallet Configuration
    |--------------------------------------------------------------------------
    |
    | Configure default wallet configuration settings that can be overridden
    | using attributes or per-wallet configuration.
    |
    */
    'wallet_configuration' => [
        'auto_create_wallet' => env('WALLET_AUTO_CREATE', true),
        'default_wallet_name' => env('WALLET_DEFAULT_NAME', 'default'),
        'enable_events' => env('WALLET_ENABLE_EVENTS', true),
        'enable_audit_log' => env('WALLET_ENABLE_AUDIT_LOG', true),
        'enable_bulk_operations' => env('WALLET_ENABLE_BULK_OPERATIONS', true),
        'freeze_rules' => [
            'auto_freeze_on_suspicious_activity' => env('WALLET_AUTO_FREEZE_SUSPICIOUS', true),
            'auto_freeze_on_limit_exceeded' => env('WALLET_AUTO_FREEZE_LIMIT_EXCEEDED', false),
        ],
        'notification_settings' => [
            'notify_on_balance_change' => env('WALLET_NOTIFY_BALANCE_CHANGE', true),
            'notify_on_transaction' => env('WALLET_NOTIFY_TRANSACTION', true),
            'notify_on_transfer' => env('WALLET_NOTIFY_TRANSFER', true),
        ],
        'security_settings' => [
            'require_confirmation' => env('WALLET_REQUIRE_CONFIRMATION', false),
            'max_failed_attempts' => env('WALLET_MAX_FAILED_ATTEMPTS', 5),
            'lockout_duration' => env('WALLET_LOCKOUT_DURATION', 900), // 15 minutes
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | Configure webhook notifications for wallet events.
    |
    */
    'webhook' => [
        'enabled' => env('WALLET_WEBHOOK_ENABLED', false),
        'url' => env('WALLET_WEBHOOK_URL'),
        'secret' => env('WALLET_WEBHOOK_SECRET'),
        'timeout' => env('WALLET_WEBHOOK_TIMEOUT', 10),
        'threshold' => env('WALLET_WEBHOOK_THRESHOLD', 1000), // Minimum amount for balance change webhooks
        'events' => [
            'wallet.balance_changed' => true,
            'transfer.completed' => true,
            'security.suspicious_activity' => true,
        ],
    ],
];
