<?php

use HWallet\LaravelMultiWallet\Enums\BalanceType;
use HWallet\LaravelMultiWallet\Models\Wallet;
use HWallet\LaravelMultiWallet\Tests\Models\User;

beforeEach(function () {
    $this->user = User::create(['name' => 'Test User', 'email' => 'test@example.com']);
});

test('it can create wallet using trait', function () {
    $wallet = $this->user->createWallet('USD', 'Main Wallet');

    expect($wallet)->toBeInstanceOf(Wallet::class);
    expect($wallet->currency)->toBe('USD');
    expect($wallet->name)->toBe('Main Wallet');
    expect($wallet->holder_id)->toBe($this->user->id);
    expect($wallet->holder_type)->toBe(get_class($this->user));
});

test('it can get or create wallet using trait', function () {
    // First call should create
    $wallet1 = $this->user->getOrCreateWallet('USD', 'Main');
    expect($wallet1)->toBeInstanceOf(Wallet::class);

    // Second call should return existing
    $wallet2 = $this->user->getOrCreateWallet('USD', 'Main');
    expect($wallet1->id)->toBe($wallet2->id);
});

test('it can get wallet by currency and name', function () {
    $wallet = $this->user->createWallet('USD', 'Savings');

    $foundWallet = $this->user->getWallet('USD', 'Savings');

    expect($wallet->id)->toBe($foundWallet->id);
});

test('it can get default wallet', function () {
    $wallet = $this->user->createWallet('USD'); // No name = default wallet

    $defaultWallet = $this->user->getDefaultWallet('USD');

    expect($wallet->id)->toBe($defaultWallet->id);
});

test('it can check if has wallet', function () {
    expect($this->user->hasWallet('USD'))->toBeFalse();

    $this->user->createWallet('USD');

    expect($this->user->hasWallet('USD'))->toBeTrue();
});

test('it can get wallets by currency', function () {
    $wallet1 = $this->user->createWallet('USD', 'Main');
    $wallet2 = $this->user->createWallet('USD', 'Savings');
    $wallet3 = $this->user->createWallet('EUR', 'European');

    $usdWallets = $this->user->getWalletsByCurrency('USD');

    expect($usdWallets)->toHaveCount(2);
    expect($usdWallets->contains($wallet1))->toBeTrue();
    expect($usdWallets->contains($wallet2))->toBeTrue();
    expect($usdWallets->contains($wallet3))->toBeFalse();
});

test('it can get total balance', function () {
    $wallet1 = $this->user->createWallet('USD', 'Main');
    $wallet2 = $this->user->createWallet('USD', 'Savings');

    $wallet1->credit(100, BalanceType::AVAILABLE);
    $wallet2->credit(50, BalanceType::AVAILABLE);

    $totalBalance = $this->user->getTotalBalance('USD');

    expect($totalBalance)->toBe(150.0);
});

test('it can get available balance', function () {
    $wallet1 = $this->user->createWallet('USD', 'Main');
    $wallet2 = $this->user->createWallet('USD', 'Savings');

    $wallet1->credit(100, BalanceType::AVAILABLE);
    $wallet1->credit(20, BalanceType::PENDING);
    $wallet2->credit(50, BalanceType::AVAILABLE);

    $availableBalance = $this->user->getAvailableBalance('USD');

    expect($availableBalance)->toBe(150.0); // Only available balance
});

test('it can transfer to another user', function () {
    $user2 = User::create(['name' => 'User 2', 'email' => 'user2@example.com']);

    $wallet1 = $this->user->createWallet('USD');
    $wallet2 = $user2->createWallet('USD');

    $wallet1->credit(200, BalanceType::AVAILABLE);

    $transfer = $this->user->transferTo($user2, 100, 'USD');

    expect($wallet1->fresh()->getBalance(BalanceType::AVAILABLE))->toBe(100.0);
    expect($wallet2->fresh()->getBalance(BalanceType::AVAILABLE))->toBe(100.0);
    expect($transfer->isConfirmed())->toBeTrue();
});

test('it can check if can afford', function () {
    $wallet = $this->user->createWallet('USD');
    $wallet->credit(100, BalanceType::AVAILABLE);

    expect($this->user->canAfford(50, 'USD'))->toBeTrue();
    expect($this->user->canAfford(100, 'USD'))->toBeTrue();
    expect($this->user->canAfford(150, 'USD'))->toBeFalse();
});

test('it returns false when checking affordability without wallet', function () {
    expect($this->user->canAfford(50, 'USD'))->toBeFalse();
});

test('it can get all transfers', function () {
    $user2 = User::create(['name' => 'User 2', 'email' => 'user2@example.com']);

    $wallet1 = $this->user->createWallet('USD');
    $wallet2 = $user2->createWallet('USD');

    $wallet1->credit(200, BalanceType::AVAILABLE);

    // Make a transfer
    $this->user->transferTo($user2, 100, 'USD');

    $transfers = $this->user->getAllTransfers();

    expect($transfers)->toHaveCount(1);
    expect($transfers->first()->from_type)->toBe($this->user->getMorphClass());
    expect($transfers->first()->from_id)->toBe($this->user->id);
});

test('it can delete empty wallet', function () {
    $wallet = $this->user->createWallet('USD', 'Empty');
    $walletId = $wallet->id;

    $result = $this->user->deleteWallet('USD', 'Empty');

    expect($result)->toBeTrue();
    expect(Wallet::find($walletId))->toBeNull();
});

test('it cannot delete wallet with balance', function () {
    $wallet = $this->user->createWallet('USD', 'WithBalance');
    $wallet->credit(100, BalanceType::AVAILABLE);

    expect(fn () => $this->user->deleteWallet('USD', 'WithBalance'))->toThrow(\Exception::class, 'Cannot delete wallet with remaining balance');
});

test('it returns false when deleting non existent wallet', function () {
    $result = $this->user->deleteWallet('USD', 'NonExistent');

    expect($result)->toBeFalse();
});
