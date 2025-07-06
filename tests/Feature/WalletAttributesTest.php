<?php

use HWallet\LaravelMultiWallet\Attributes\WalletConfiguration;
use HWallet\LaravelMultiWallet\Models\Wallet;
use HWallet\LaravelMultiWallet\Services\WalletManager;
use HWallet\LaravelMultiWallet\Tests\Models\User;

// Test model with wallet configuration attributes
#[WalletConfiguration(
    defaultCurrency: 'USD',
    allowedCurrencies: ['USD', 'EUR', 'GBP'],
    autoCreateWallet: true,
    walletName: 'Primary Wallet',
    enableEvents: true,
    enableAuditLog: true,
    transactionLimits: ['min_amount' => 0.01, 'max_amount' => 10000.00],
    walletLimits: ['max_balance' => 100000.00],
    enableBulkOperations: true
)]
class UserWithWalletConfig extends User
{
    protected $table = 'users';
}

// Test model with minimal configuration
#[WalletConfiguration(
    defaultCurrency: 'EUR',
    autoCreateWallet: false
)]
class UserWithMinimalConfig extends User
{
    protected $table = 'users';
}

// Test model with no configuration
class UserWithoutConfig extends User
{
    protected $table = 'users';
}

beforeEach(function () {
    $this->walletManager = app(WalletManager::class);
});

test('it can read wallet configuration from attributes', function () {
    $user = new UserWithWalletConfig;

    $config = $user->getWalletConfiguration();

    expect($config)->toHaveKeys([
        'default_currency',
        'allowed_currencies',
        'auto_create_wallet',
        'wallet_name',
        'enable_events',
        'enable_audit_log',
        'transaction_limits',
        'wallet_limits',
        'enable_bulk_operations',
    ]);

    expect($config['default_currency'])->toBe('USD');
    expect($config['allowed_currencies'])->toBe(['USD', 'EUR', 'GBP']);
    expect($config['auto_create_wallet'])->toBeTrue();
    expect($config['wallet_name'])->toBe('Primary Wallet');
    expect($config['enable_events'])->toBeTrue();
    expect($config['enable_audit_log'])->toBeTrue();
    expect($config['enable_bulk_operations'])->toBeTrue();
    expect($config['transaction_limits'])->toEqual(['min_amount' => 0.01, 'max_amount' => 10000.00]);
    expect($config['wallet_limits'])->toEqual(['max_balance' => 100000.00]);
});

test('it can read minimal wallet configuration', function () {
    $user = new UserWithMinimalConfig;

    $config = $user->getWalletConfiguration();

    expect($config)->toHaveKeys(['default_currency', 'auto_create_wallet']);
    expect($config['default_currency'])->toBe('EUR');
    expect($config['auto_create_wallet'])->toBeFalse();
});

test('it returns empty configuration for models without attributes', function () {
    $user = new UserWithoutConfig;

    $config = $user->getWalletConfiguration();

    expect($config)->toBeArray();
    expect($config)->toBeEmpty();
});

test('it can auto-create wallet based on configuration', function () {
    $user = new UserWithWalletConfig;
    $user->name = 'Test User';
    $user->email = 'test@example.com';
    $user->password = 'password';
    $user->save();

    $wallet = $user->autoCreateWallet();

    expect($wallet)->toBeInstanceOf(Wallet::class);
    expect($wallet->currency)->toBe('USD');
    expect($wallet->name)->toBe('Primary Wallet');
    expect($wallet->holder_id)->toBe($user->id);
});

test('it returns null when auto-create is disabled', function () {
    $user = new UserWithMinimalConfig;
    $user->name = 'Test User';
    $user->email = 'test@example.com';
    $user->password = 'password';
    $user->save();

    $wallet = $user->autoCreateWallet();

    expect($wallet)->toBeNull();
});

test('it can get default wallet from configuration', function () {
    $user = new UserWithWalletConfig;
    $user->name = 'Test User';
    $user->email = 'test@example.com';
    $user->password = 'password';
    $user->save();

    // First create the wallet
    $createdWallet = $user->autoCreateWallet();

    // Then get it using configuration
    $wallet = $user->getDefaultWalletFromConfig();

    expect($wallet)->toBeInstanceOf(Wallet::class);
    expect($wallet->id)->toBe($createdWallet->id);
});

test('it can create multiple wallets from configuration', function () {
    $user = new UserWithWalletConfig;
    $user->name = 'Test User';
    $user->email = 'test@example.com';
    $user->password = 'password';
    $user->save();

    $wallets = $user->createWalletsFromConfig();

    expect($wallets)->toHaveCount(3);
    expect($wallets)->toHaveKeys(['USD', 'EUR', 'GBP']);
    expect($wallets['USD'])->toBeInstanceOf(Wallet::class);
    expect($wallets['EUR'])->toBeInstanceOf(Wallet::class);
    expect($wallets['GBP'])->toBeInstanceOf(Wallet::class);
});

test('it can get wallet configuration values', function () {
    $user = new UserWithWalletConfig;

    expect($user->getWalletConfigValue('default_currency'))->toBe('USD');
    expect($user->getWalletConfigValue('auto_create_wallet'))->toBeTrue();
    expect($user->getWalletConfigValue('non_existent_key'))->toBeNull();
    expect($user->getWalletConfigValue('non_existent_key', 'default_value'))->toBe('default_value');
});

test('it can check if wallet events are enabled', function () {
    $userWithEvents = new UserWithWalletConfig;
    $userWithoutConfig = new UserWithoutConfig;

    expect($userWithEvents->areWalletEventsEnabled())->toBeTrue();
    expect($userWithoutConfig->areWalletEventsEnabled())->toBeTrue(); // Default is true
});

test('it can check if wallet audit log is enabled', function () {
    $userWithAudit = new UserWithWalletConfig;
    $userWithoutConfig = new UserWithoutConfig;

    expect($userWithAudit->isWalletAuditLogEnabled())->toBeTrue();
    expect($userWithoutConfig->isWalletAuditLogEnabled())->toBeFalse(); // Default is false
});

test('it can check if bulk operations are enabled', function () {
    $userWithBulk = new UserWithWalletConfig;
    $userWithoutConfig = new UserWithoutConfig;

    expect($userWithBulk->areBulkOperationsEnabled())->toBeTrue();
    expect($userWithoutConfig->areBulkOperationsEnabled())->toBeTrue(); // Default is true
});

test('it can get allowed currencies', function () {
    $userWithCurrencies = new UserWithWalletConfig;
    $userWithoutConfig = new UserWithoutConfig;

    expect($userWithCurrencies->getAllowedCurrencies())->toBe(['USD', 'EUR', 'GBP']);
    expect($userWithoutConfig->getAllowedCurrencies())->toBeArray();
    expect($userWithoutConfig->getAllowedCurrencies())->toBeEmpty();
});

test('it can get wallet transaction limits', function () {
    $userWithLimits = new UserWithWalletConfig;
    $userWithoutConfig = new UserWithoutConfig;

    expect($userWithLimits->getWalletTransactionLimits())->toEqual(['min_amount' => 0.01, 'max_amount' => 10000.00]);
    expect($userWithoutConfig->getWalletTransactionLimits())->toBeArray();
    expect($userWithoutConfig->getWalletTransactionLimits())->toBeEmpty();
});

test('it can get wallet limits', function () {
    $userWithLimits = new UserWithWalletConfig;
    $userWithoutConfig = new UserWithoutConfig;

    expect($userWithLimits->getWalletLimits())->toEqual(['max_balance' => 100000.00]);
    expect($userWithoutConfig->getWalletLimits())->toBeArray();
    expect($userWithoutConfig->getWalletLimits())->toBeEmpty();
});

test('it can create wallet with configuration using wallet manager', function () {
    $user = new UserWithWalletConfig;
    $user->name = 'Test User';
    $user->email = 'test@example.com';
    $user->password = 'password';
    $user->save();

    $wallet = $this->walletManager->createWalletWithConfig($user);

    expect($wallet)->toBeInstanceOf(Wallet::class);
    expect($wallet->currency)->toBe('USD');
    expect($wallet->name)->toBe('Primary Wallet');
});

test('it can create wallet with configuration override', function () {
    $user = new UserWithWalletConfig;
    $user->name = 'Test User';
    $user->email = 'test@example.com';
    $user->password = 'password';
    $user->save();

    $wallet = $this->walletManager->createWalletWithConfig($user, 'EUR', 'Custom Wallet');

    expect($wallet)->toBeInstanceOf(Wallet::class);
    expect($wallet->currency)->toBe('EUR');
    expect($wallet->name)->toBe('Custom Wallet');
});

test('it can batch create wallets using wallet manager', function () {
    $user = new UserWithWalletConfig;
    $user->name = 'Test User';
    $user->email = 'test@example.com';
    $user->password = 'password';
    $user->save();

    $currencies = [
        'USD' => ['name' => 'USD Wallet', 'attributes' => ['priority' => 1]],
        'EUR' => ['name' => 'EUR Wallet', 'attributes' => ['priority' => 2]],
        'GBP' => ['name' => 'GBP Wallet', 'attributes' => ['priority' => 3]],
    ];

    $wallets = $this->walletManager->batchCreateWallets($user, $currencies);

    expect($wallets)->toHaveCount(3);
    expect($wallets)->toHaveKeys(['USD', 'EUR', 'GBP']);
    expect($wallets['USD']->name)->toBe('USD Wallet');
    expect($wallets['EUR']->name)->toBe('EUR Wallet');
    expect($wallets['GBP']->name)->toBe('GBP Wallet');
});

test('it can perform bulk operations using trait methods', function () {
    $user = new UserWithWalletConfig;
    $user->name = 'Test User';
    $user->email = 'test@example.com';
    $user->password = 'password';
    $user->save();

    // Create wallets first
    $wallets = $user->createWalletsFromConfig();

    // Add some initial balance
    $wallets['USD']->credit(1000.00);
    $wallets['EUR']->credit(2000.00);

    // Perform bulk credit
    $operations = [
        ['currency' => 'USD', 'amount' => 100.00],
        ['currency' => 'EUR', 'amount' => 200.00],
    ];

    $result = $user->bulkCreditWallets($operations);

    expect($result['success'])->toBeTrue();
    expect($result['successful_operations'])->toBe(2);

    // Verify balances
    expect($wallets['USD']->fresh()->getBalance('available'))->toBe(1100.00);
    expect($wallets['EUR']->fresh()->getBalance('available'))->toBe(2200.00);
});

test('it handles bulk operations when wallet does not exist', function () {
    $user = new UserWithWalletConfig;
    $user->name = 'Test User';
    $user->email = 'test@example.com';
    $user->password = 'password';
    $user->save();

    $operations = [
        ['currency' => 'JPY', 'amount' => 100.00], // Wallet doesn't exist
    ];

    $result = $user->bulkCreditWallets($operations);

    expect($result['success'])->toBeFalse();
    expect($result['failed_operations'])->toBe(1);
});

test('it caches wallet configuration', function () {
    $user = new UserWithWalletConfig;

    // First call should load configuration
    $config1 = $user->getWalletConfiguration();

    // Second call should return cached configuration
    $config2 = $user->getWalletConfiguration();

    expect($config1)->toBe($config2);
    expect($config1)->toEqual($config2);
});

test('wallet configuration attribute can be converted to array', function () {
    $config = new WalletConfiguration(
        defaultCurrency: 'USD',
        allowedCurrencies: ['USD', 'EUR'],
        autoCreateWallet: true,
        enableEvents: true
    );

    $array = $config->toArray();

    expect($array)->toHaveKeys(['default_currency', 'allowed_currencies', 'auto_create_wallet', 'enable_events']);
    expect($array['default_currency'])->toBe('USD');
    expect($array['allowed_currencies'])->toBe(['USD', 'EUR']);
    expect($array['auto_create_wallet'])->toBeTrue();
    expect($array['enable_events'])->toBeTrue();
});

test('wallet configuration attribute filters null values', function () {
    $config = new WalletConfiguration(
        defaultCurrency: 'USD',
        allowedCurrencies: null,
        autoCreateWallet: true,
        enableEvents: null
    );

    $array = $config->toArray();

    expect($array)->toHaveKeys(['default_currency', 'auto_create_wallet']);
    expect($array)->not->toHaveKey('allowed_currencies');
    expect($array)->not->toHaveKey('enable_events');
});

test('it can get enabled balance types from configuration', function () {
    $userWithBalanceTypes = new UserWithWalletConfig;
    $userWithoutConfig = new UserWithoutConfig;

    expect($userWithBalanceTypes->getEnabledBalanceTypes())->toBeArray();
    expect($userWithoutConfig->getEnabledBalanceTypes())->toBe(['available', 'pending', 'frozen', 'trial']);
});

test('it can check if balance type is enabled', function () {
    $user = new UserWithWalletConfig;

    expect($user->isBalanceTypeEnabled('available'))->toBeTrue();
    expect($user->isBalanceTypeEnabled('pending'))->toBeTrue();
    expect($user->isBalanceTypeEnabled(\HWallet\LaravelMultiWallet\Enums\BalanceType::AVAILABLE))->toBeTrue();
});

test('it can get uniqueness settings', function () {
    $user = new UserWithWalletConfig;

    expect($user->isUniquenessEnabled())->toBeTrue();
    expect($user->getUniquenessStrategy())->toBe('default');
});

test('it can get fee calculation settings', function () {
    $user = new UserWithWalletConfig;

    $feeSettings = $user->getFeeCalculationSettings();
    expect($feeSettings)->toBeArray();
});

test('it can get exchange rate configuration', function () {
    $user = new UserWithWalletConfig;

    $exchangeConfig = $user->getExchangeRateConfig();
    expect($exchangeConfig)->toBeArray();
});

test('it can get webhook settings', function () {
    $user = new UserWithWalletConfig;

    $webhookSettings = $user->getWebhookSettings();
    expect($webhookSettings)->toBeArray();
});

test('it can get notification settings', function () {
    $user = new UserWithWalletConfig;

    $notificationSettings = $user->getNotificationSettings();
    expect($notificationSettings)->toBeArray();
});

test('it can get security settings', function () {
    $user = new UserWithWalletConfig;

    $securitySettings = $user->getSecuritySettings();
    expect($securitySettings)->toBeArray();
});

test('it can get balance and transaction limits', function () {
    $user = new UserWithWalletConfig;

    expect($user->getMaxBalanceLimit())->toBe(100000.00);
    expect($user->getMinBalanceLimit())->toBe(0.0);
    expect($user->getMaxTransactionAmount())->toBe(10000.00);
    expect($user->getMinTransactionAmount())->toBe(0.01);
    expect($user->getDailyTransactionLimit())->toBeNull();
});

test('it can validate transaction amounts', function () {
    $user = new UserWithWalletConfig;

    expect($user->validateTransactionAmount(100.00))->toBeTrue();
    expect($user->validateTransactionAmount(0.005))->toBeFalse(); // Below minimum
    expect($user->validateTransactionAmount(15000.00))->toBeFalse(); // Above maximum
});

test('it can validate wallet balances', function () {
    $user = new UserWithWalletConfig;

    expect($user->validateWalletBalance(50000.00))->toBeTrue();
    expect($user->validateWalletBalance(-1.00))->toBeFalse(); // Below minimum
    expect($user->validateWalletBalance(150000.00))->toBeFalse(); // Above maximum
});

test('it can get default currency and wallet name', function () {
    $user = new UserWithWalletConfig;

    expect($user->getDefaultCurrency())->toBe('USD');
    expect($user->getDefaultWalletName())->toBe('Primary Wallet');
});

test('it can create wallet with validation', function () {
    $user = new UserWithWalletConfig;
    $user->name = 'Test User';
    $user->email = 'test@example.com';
    $user->password = 'password';
    $user->save();

    // Should work with allowed currency
    $wallet = $user->createWalletWithValidation('USD', 'Test Wallet');
    expect($wallet)->toBeInstanceOf(Wallet::class);
    expect($wallet->currency)->toBe('USD');

    // Should fail with disallowed currency
    expect(fn () => $user->createWalletWithValidation('JPY', 'JPY Wallet'))
        ->toThrow(\InvalidArgumentException::class);
});

test('it can sync with global configuration', function () {
    $user = new UserWithWalletConfig;

    // This should not throw an exception
    $user->syncWithGlobalConfiguration();

    $config = $user->getWalletConfiguration();
    expect($config)->toBeArray();
});

test('it can get wallet configuration interface', function () {
    $user = new UserWithWalletConfig;

    $configInterface = $user->getWalletConfigurationInterface();
    expect($configInterface)->toBeInstanceOf(\HWallet\LaravelMultiWallet\Contracts\WalletConfigurationInterface::class);
});

test('it can get freeze rules and metadata schema', function () {
    $user = new UserWithWalletConfig;

    $freezeRules = $user->getFreezeRules();
    $metadataSchema = $user->getMetadataSchema();

    expect($metadataSchema)->toBeArray();
});

test('uniqueness validation works correctly', function () {
    $user = new UserWithWalletConfig;
    $user->name = 'Test User';
    $user->email = 'test@example.com';
    $user->password = 'password';
    $user->save();

    // Create first wallet
    $wallet1 = $user->createWalletWithValidation('USD', 'Test Wallet');
    expect($wallet1)->toBeInstanceOf(Wallet::class);

    // Try to create duplicate wallet - should fail
    expect(fn () => $user->createWalletWithValidation('USD', 'Test Wallet'))
        ->toThrow(\InvalidArgumentException::class, 'Wallet already exists for this currency and name combination');
});

test('it can perform enhanced validation operations', function () {
    $user = new UserWithWalletConfig;
    $user->name = 'Test User';
    $user->email = 'test@example.com';
    $user->password = 'password';
    $user->save();

    $wallet = $user->createWallet('USD', 'Test Wallet');
    $wallet->credit(1000.00);

    // Test canAfford method
    expect($user->canAfford(500.00, 'USD'))->toBeTrue();
    expect($user->canAfford(1500.00, 'USD'))->toBeFalse();
    expect($user->canAfford(100.00, 'EUR'))->toBeFalse(); // Wallet doesn't exist
});

test('it can perform enhanced transfer operations', function () {
    $sender = new UserWithWalletConfig;
    $sender->name = 'Sender User';
    $sender->email = 'sender@example.com';
    $sender->password = 'password';
    $sender->save();

    $recipient = new UserWithWalletConfig;
    $recipient->name = 'Recipient User';
    $recipient->email = 'recipient@example.com';
    $recipient->password = 'password';
    $recipient->save();

    $senderWallet = $sender->createWallet('USD', 'Sender Wallet');
    $senderWallet->credit(1000.00);

    $transfer = $sender->transferTo($recipient, 100.00, 'USD', [
        'description' => 'Test transfer',
    ]);

    expect($transfer)->toBeInstanceOf(\HWallet\LaravelMultiWallet\Models\Transfer::class);
    expect($senderWallet->fresh()->getBalance('available'))->toBe(900.00);

    $recipientWallet = $recipient->getWallet('USD');
    expect($recipientWallet->getBalance('available'))->toBe(100.00);
});

test('it can get all transfers for a model', function () {
    $user1 = new UserWithWalletConfig;
    $user1->name = 'User 1';
    $user1->email = 'user1@example.com';
    $user1->password = 'password';
    $user1->save();

    $user2 = new UserWithWalletConfig;
    $user2->name = 'User 2';
    $user2->email = 'user2@example.com';
    $user2->password = 'password';
    $user2->save();

    $wallet1 = $user1->createWallet('USD', 'Wallet 1');
    $wallet1->credit(1000.00);

    // Make some transfers
    $user1->transferTo($user2, 100.00, 'USD');
    $user1->transferTo($user2, 50.00, 'USD');

    $allTransfers = $user1->getAllTransfers();
    expect($allTransfers->count())->toBe(2);
});
