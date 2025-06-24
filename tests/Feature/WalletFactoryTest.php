<?php

use HWallet\LaravelMultiWallet\Models\Wallet;
use HWallet\LaravelMultiWallet\Services\WalletFactory;
use HWallet\LaravelMultiWallet\Tests\Models\User;

beforeEach(function () {
    $this->walletFactory = app(WalletFactory::class);
});

test('can create wallet instance', function () {
    $user = User::factory()->create();

    $wallet = $this->walletFactory->create($user, 'USD', 'Test Wallet');

    expect($wallet)->toBeInstanceOf(Wallet::class);
    expect($wallet->holder_id)->toBe($user->getKey());
    expect($wallet->holder_type)->toBe(User::class);
    expect($wallet->currency)->toBe('USD');
    expect($wallet->name)->toBe('Test Wallet');
    expect($wallet->slug)->not->toBeEmpty();
});

test('can create and save wallet', function () {
    $user = User::factory()->create();

    $wallet = $this->walletFactory->createAndSave($user, 'EUR', 'Euro Wallet');

    expect($wallet->exists)->toBeTrue();
    $this->assertDatabaseHas('wallets', [
        'id' => $wallet->id,
        'currency' => 'EUR',
        'name' => 'Euro Wallet',
    ]);
});

test('can create default wallet', function () {
    $user = User::factory()->create();

    $wallet = $this->walletFactory->createDefault($user);

    expect($wallet->exists)->toBeTrue();
    expect($wallet->currency)->toBe(config('multi-wallet.default_currency', 'USD'));
    expect($wallet->description)->toBe('Default wallet');
    expect($wallet->meta['is_default'] ?? false)->toBeTrue();
});

test('can create multiple wallets', function () {
    $user = User::factory()->create();

    $currencies = [
        'USD' => ['name' => 'US Dollar Wallet'],
        'EUR' => ['name' => 'Euro Wallet'],
        'GBP' => ['name' => 'British Pound Wallet'],
    ];

    $wallets = $this->walletFactory->createMultiple($user, $currencies);

    expect($wallets)->toHaveCount(3);
    expect($wallets)->toHaveKey('USD');
    expect($wallets)->toHaveKey('EUR');
    expect($wallets)->toHaveKey('GBP');

    expect($wallets['USD']->name)->toBe('US Dollar Wallet');
    expect($wallets['EUR']->name)->toBe('Euro Wallet');
    expect($wallets['GBP']->name)->toBe('British Pound Wallet');
});

test('can create wallet with initial balance', function () {
    $user = User::factory()->create();

    $wallet = $this->walletFactory->createWithBalance($user, 'USD', 100.00, 'available', 'Funded Wallet');

    expect($wallet->exists)->toBeTrue();
    expect($wallet->getBalance('available'))->toBe(100.00);
    expect($wallet->name)->toBe('Funded Wallet');

    // Check that a transaction was created
    expect($wallet->transactions)->toHaveCount(1);
    expect((float) $wallet->transactions->first()->amount)->toBe(100.00);
});

test('generates unique slug', function () {
    $user = User::factory()->create();

    // Create first wallet
    $wallet1 = $this->walletFactory->createAndSave($user, 'USD', 'Test Wallet');

    // Create second wallet with same name
    $wallet2 = $this->walletFactory->createAndSave($user, 'EUR', 'Test Wallet');

    expect($wallet1->slug)->not->toBe($wallet2->slug);
    expect($wallet1->slug)->toContain('test-wallet');
    expect($wallet2->slug)->toContain('test-wallet');
});

test('creates currency based slug when no name', function () {
    $user = User::factory()->create();

    $wallet = $this->walletFactory->createAndSave($user, 'JPY');

    expect($wallet->slug)->toContain('jpy-wallet');
});
