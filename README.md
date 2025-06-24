# Laravel Multi-Currency Wallet Management Package

[![Latest Version on Packagist](https://img.shields.io/packagist/v/hwallet/laravel-multi-wallet.svg?style=flat-square)](https://packagist.org/packages/hwallet/laravel-multi-wallet)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/hwallet/laravel-multi-wallet/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/hwallet/laravel-multi-wallet/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/hwallet/laravel-multi-wallet/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/hwallet/laravel-multi-wallet/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/hwallet/laravel-multi-wallet.svg?style=flat-square)](https://packagist.org/packages/hwallet/laravel-multi-wallet)

A comprehensive Laravel package for managing multi-currency wallets with advanced features including multiple balance types, transfers, fees, discounts, and configurable exchange rates. Perfect for e-commerce, fintech, and any application requiring robust financial transaction management.

## ✨ Key Features

- 🏦 **Multi-Currency Support**: Manage wallets for various currencies with configurable exchange rates
- 💰 **Multiple Balance Types**: Support for Available, Pending, Frozen, and Trial balances  
- 🔄 **Advanced Transfers**: Transfer between wallets with fees, discounts, and status tracking
- 🎯 **Polymorphic Relations**: Flexible model associations - attach wallets to any model
- 📊 **Transaction Tracking**: Comprehensive transaction history with metadata support
- ⚙️ **Configurable Architecture**: Runtime configuration with extensible interfaces
- 🔒 **Type Safety**: Built with PHP 8.1+ features and strict typing
- 🧪 **Fully Tested**: 100% test coverage with Pest testing framework
- 📝 **Event System**: Rich event system for wallet operations
- 🎨 **Clean Architecture**: SOLID principles with repository and service patterns

## 📋 Requirements

- PHP 8.1+
- Laravel 10.0+

## 🚀 Installation

Install the package via Composer:

```bash
composer require hwallet/laravel-multi-wallet
```

Publish and run the migrations:

```bash
php artisan vendor:publish --provider="HWallet\LaravelMultiWallet\LaravelMultiWalletServiceProvider" --tag="migrations"
php artisan migrate
```

Optionally, publish the configuration file:

```bash
php artisan vendor:publish --provider="HWallet\LaravelMultiWallet\LaravelMultiWalletServiceProvider" --tag="config"
```

## 🎯 Quick Start

### 1. Add the HasWallets trait to your model

```php
use HWallet\LaravelMultiWallet\Traits\HasWallets;

class User extends Authenticatable
{
    use HasWallets;
    
    // ... your model code
}
```

### 2. Create and manage wallets

```php
$user = User::find(1);

// Create a basic wallet
$wallet = $user->createWallet('USD', 'Main Wallet');

// Create with metadata
$wallet = $user->createWallet('EUR', 'European Wallet', [
    'description' => 'For European transactions',
    'meta' => ['region' => 'EU', 'priority' => 'high']
]);

// Credit the wallet
$transaction = $wallet->credit(100.00, 'available', [
    'description' => 'Initial deposit',
    'reference' => 'DEP-001'
]);

// Check balance
echo $wallet->getBalance('available'); // 100.00
```

### 3. Transfer between users

```php
$sender = User::find(1);
$recipient = User::find(2);

// Simple transfer
$transfer = $sender->transferTo($recipient, 50.00, 'USD');

// Transfer with fee and metadata
$transfer = $sender->transferTo($recipient, 100.00, 'USD', [
    'fee' => 2.50,
    'description' => 'Payment for services',
    'reference' => 'TXN-12345'
]);

echo $transfer->getNetAmount(); // 102.50 (amount + fee)
echo $transfer->status->value; // 'confirmed'
```

## 📖 Comprehensive Guide

### Balance Types

The package supports four distinct balance types for advanced financial management:

```php
$wallet = $user->createWallet('USD', 'Main Wallet');

// Available Balance - Ready for use
$wallet->credit(1000.00, 'available');
echo $wallet->getBalance('available'); // 1000.00

// Pending Balance - Funds awaiting confirmation
$wallet->moveToPending(200.00, 'Processing payment');
$wallet->confirmPending(200.00, 'Payment confirmed');

// Frozen Balance - Temporarily locked funds
$wallet->freeze(100.00, 'Security review');
$wallet->unfreeze(100.00, 'Review completed');

// Trial Balance - Promotional or test credits
$wallet->addTrialBalance(50.00, 'Welcome bonus');
$wallet->convertTrialToAvailable(25.00, 'Trial period ended');

// Get comprehensive balance summary
$summary = $wallet->getBalanceSummary();
/*
[
    'available' => 825.00,
    'pending' => 0.00,
    'frozen' => 0.00,
    'trial' => 25.00,
    'total' => 850.00
]
*/
```

### Advanced Transfer Operations

```php
use HWallet\LaravelMultiWallet\Services\WalletManager;

$manager = app(WalletManager::class);
$fromWallet = $user1->getWallet('USD', 'Main');
$toWallet = $user2->getWallet('USD', 'Savings');

// Transfer with fee and discount
$transfer = $manager->transfer($fromWallet, $toWallet, 100.00, [
    'fee' => 5.00,        // Service fee
    'discount' => 2.00,   // Loyalty discount
    'description' => 'Discounted transfer',
    'meta' => ['promo_code' => 'SAVE20']
]);

// Check transfer details
echo $transfer->getGrossAmount();      // 100.00 (original amount)
echo $transfer->getFee();              // 5.00
echo $transfer->getDiscount();         // 2.00
echo $transfer->getNetAmount();        // 103.00 (100 + 5 - 2)
echo $transfer->getTransferredAmount(); // 100.00 (amount received)

// Transfer status management
$transfer->markAsPending();
$transfer->markAsConfirmed();
$transfer->markAsRejected();
```

### Working with Multiple Currencies

```php
// Configure exchange rates in config/multi-wallet.php
$user->createWallet('USD', 'US Dollar Wallet');
$user->createWallet('EUR', 'Euro Wallet');
$user->createWallet('GBP', 'British Pound Wallet');

// Get wallets by currency
$usdWallet = $user->getWallet('USD');
$eurWallet = $user->getWallet('EUR');

// Get all wallets
$allWallets = $user->wallets;

// Get wallets by currency
$usdWallets = $user->getWalletsByCurrency('USD');

// Check total balance across all currencies
$totalUsd = $user->getTotalBalance('USD');
$totalEur = $user->getTotalBalance('EUR');
```

### Event System

The package dispatches events for all major operations:

```php
use HWallet\LaravelMultiWallet\Events\WalletCreated;
use HWallet\LaravelMultiWallet\Events\TransactionCreated;
use HWallet\LaravelMultiWallet\Events\TransferCompleted;

// Listen to wallet events
Event::listen(WalletCreated::class, function ($event) {
    Log::info('New wallet created', [
        'wallet_id' => $event->wallet->id,
        'holder' => $event->wallet->holder_type,
        'currency' => $event->wallet->currency
    ]);
});

Event::listen(TransferCompleted::class, function ($event) {
    // Send notification, update analytics, etc.
    Notification::send($event->transfer->to, new TransferReceived($event->transfer));
});
```

### Using the Facade

```php
use HWallet\LaravelMultiWallet\Facades\LaravelMultiWallet;

// Create wallet via facade
$wallet = LaravelMultiWallet::createWallet($user, 'USD', 'Main Wallet');

// Transfer between wallets
$transfer = LaravelMultiWallet::transfer($fromWallet, $toWallet, 100.00, [
    'description' => 'Facade transfer'
]);

// Get balance summary
$summary = LaravelMultiWallet::getBalanceSummary($user, 'USD');
```

### Custom Exchange Rate Providers

Implement your own exchange rate logic:

```php
use HWallet\LaravelMultiWallet\Contracts\ExchangeRateProviderInterface;

class ApiExchangeRateProvider implements ExchangeRateProviderInterface
{
    public function getRate(string $from, string $to): float
    {
        // Fetch from external API (e.g., CurrencyLayer, Fixer.io)
        $response = Http::get("https://api.exchangerate-api.com/v4/latest/{$from}");
        return $response->json()['rates'][$to] ?? 1.0;
    }
    
    public function convert(float $amount, string $from, string $to): float
    {
        return $amount * $this->getRate($from, $to);
    }
    
    public function getSupportedCurrencies(): array
    {
        return ['USD', 'EUR', 'GBP', 'JPY', 'CAD', 'AUD'];
    }
}

// Register in service provider
$this->app->singleton(ExchangeRateProviderInterface::class, ApiExchangeRateProvider::class);
```

## ⚙️ Configuration

Publish and customize the configuration file:

```php
// config/multi-wallet.php
return [
    // Default settings
    'default_currency' => 'USD',
    'default_balance_type' => 'available',
    
    // Wallet constraints
    'wallet_limits' => [
        'max_balance' => null,           // No limit
        'min_balance' => 0,              // Cannot go negative
        'max_wallets_per_holder' => null // No limit
    ],
    
    // Transaction constraints
    'transaction_limits' => [
        'max_amount' => null,     // No limit
        'min_amount' => 0.01,     // Minimum transaction
    ],
    
    // Transfer settings
    'transfer_settings' => [
        'auto_confirm' => true,           // Auto-confirm transfers
        'max_fee_percentage' => 10,       // Max 10% fee
        'max_discount_percentage' => 100, // Max 100% discount
    ],
    
    // Supported currencies
    'supported_currencies' => [
        'USD', 'EUR', 'GBP', 'JPY', 'CAD', 'AUD', 'CHF', 'CNY'
    ],
    
    // Static exchange rates (use custom provider for dynamic rates)
    'exchange_rates' => [
        'USD_EUR' => 0.85,
        'EUR_USD' => 1.18,
        'USD_GBP' => 0.73,
        'GBP_USD' => 1.37,
    ],
    
    // Database table names
    'table_names' => [
        'wallets' => 'wallets',
        'transactions' => 'transactions',
        'transfers' => 'transfers',
    ],
    
    // Enable/disable features
    'features' => [
        'enforce_uniqueness' => false,    // One wallet per currency per holder
        'soft_deletes' => true,           // Soft delete support
        'uuid_generation' => true,        // Generate UUIDs
    ]
];
```

## 🔍 Query Scopes and Relationships

```php
// Transaction queries
$wallet->transactions()
    ->confirmed()
    ->byType('credit')
    ->byBalanceType('available')
    ->where('amount', '>', 100)
    ->get();

// Transfer queries
$user->transfersFrom()
    ->confirmed()
    ->where('created_at', '>=', now()->subDays(30))
    ->get();

$user->transfersTo()
    ->pending()
    ->with(['from', 'deposit', 'withdraw'])
    ->get();

// Wallet queries with relationships
$wallets = Wallet::with(['holder', 'transactions', 'transfersFrom', 'transfersTo'])
    ->where('currency', 'USD')
    ->where('available_balance', '>', 1000)
    ->get();
```

## 🧪 Testing

The package includes comprehensive tests. Run them with:

```bash
# Run all tests
composer test

# Run with coverage
composer test-coverage

# Run specific test suite
vendor/bin/pest tests/Feature/WalletTest.php
```

## 📊 Performance Considerations

- **Database Indexes**: The package creates appropriate indexes for optimal query performance
- **Eager Loading**: Use `with()` to avoid N+1 queries when loading relationships
- **Batch Operations**: For bulk operations, consider using database transactions
- **Caching**: Consider caching exchange rates and wallet balances for high-traffic applications

```php
// Efficient wallet loading
$users = User::with(['wallets.transactions' => function ($query) {
    $query->confirmed()->latest()->limit(10);
}])->get();

// Batch operations
DB::transaction(function () use ($transfers) {
    foreach ($transfers as $transferData) {
        $this->processTransfer($transferData);
    }
});
```

## 🔐 Security Best Practices

- **Validation**: Always validate amounts and currencies before operations
- **Authorization**: Implement proper authorization checks in your controllers
- **Audit Trail**: All transactions are automatically logged with metadata
- **Immutable Records**: Transactions are immutable once created

```php
// Example authorization
Gate::define('transfer-funds', function ($user, $fromWallet) {
    return $user->id === $fromWallet->holder_id;
});

// Example validation
$request->validate([
    'amount' => 'required|numeric|min:0.01|max:10000',
    'currency' => 'required|in:USD,EUR,GBP',
    'recipient_id' => 'required|exists:users,id'
]);
```

## 🚀 Production Deployment

Before deploying to production:

1. **Run migrations**: Ensure all database migrations are applied
2. **Configure queues**: For high-volume applications, queue transaction processing
3. **Set up monitoring**: Monitor wallet balances and transaction volumes
4. **Backup strategy**: Implement regular database backups
5. **Rate limiting**: Implement rate limiting for transfer endpoints

## 🤝 Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## 🔒 Security

If you discover any security-related issues, please email mohamedhabibwork@gmail.com instead of using the issue tracker.

## 📄 License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## 🙏 Credits

- [Mohamed Habib](https://github.com/hwallet)
- [All Contributors](../../contributors)

## 📚 Related Packages

- [Laravel Cashier](https://laravel.com/docs/billing) - For subscription billing
- [Laravel Sanctum](https://laravel.com/docs/sanctum) - For API authentication
- [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission) - For role-based access control
