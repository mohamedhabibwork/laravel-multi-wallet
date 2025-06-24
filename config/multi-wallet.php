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
            // Add custom event listeners here
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
