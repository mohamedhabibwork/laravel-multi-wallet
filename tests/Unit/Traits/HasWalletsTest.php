<?php

use HWallet\LaravelMultiWallet\Models\Wallet;
use HWallet\LaravelMultiWallet\Tests\Models\User;

beforeEach(function () {
    $this->user = $this->createUser();
});

test('it can create wallet', function () {
    $wallet = $this->user->createWallet('USD', 'Test Wallet');

    expect($wallet)->toBeInstanceOf(Wallet::class);
    expect($wallet->currency)->toBe('USD');
    expect($wallet->name)->toBe('Test Wallet');
    expect($wallet->holder_id)->toBe($this->user->id);
    expect($wallet->holder_type)->toBe(User::class);
});

test('it can create wallet without name', function () {
    $wallet = $this->user->createWallet('EUR');

    expect($wallet)->toBeInstanceOf(Wallet::class);
    expect($wallet->currency)->toBe('EUR');
    expect($wallet->name)->toBeNull();
    expect($wallet->slug)->toBe('eur');
});

test('it can create wallet with description', function () {
    $wallet = $this->user->createWallet('GBP', 'Pound Wallet', ['description' => 'British Pounds']);

    expect($wallet->description)->toBe('British Pounds');
});

test('it can create wallet with meta', function () {
    $meta = ['bank_account' => '123456789'];
    $wallet = $this->user->createWallet('USD', 'Bank Wallet', ['meta' => $meta]);

    expect($wallet->meta)->toBe($meta);
});

test('it can get wallets', function () {
    $wallet1 = $this->user->createWallet('USD', 'USD Wallet');
    $wallet2 = $this->user->createWallet('EUR', 'EUR Wallet');

    $wallets = $this->user->wallets;

    expect($wallets)->toHaveCount(2);
    expect($wallets->contains($wallet1))->toBeTrue();
    expect($wallets->contains($wallet2))->toBeTrue();
});

test('it can get wallet by currency', function () {
    $usdWallet = $this->user->createWallet('USD', 'USD Wallet');
    $eurWallet = $this->user->createWallet('EUR', 'EUR Wallet');

    $foundWallet = $this->user->getWallet('USD');

    expect($foundWallet->id)->toBe($usdWallet->id);
});

test('it returns null for nonexistent currency', function () {
    $this->user->createWallet('USD', 'USD Wallet');

    $foundWallet = $this->user->getWallet('EUR');

    expect($foundWallet)->toBeNull();
});

test('it can get wallet by slug', function () {
    $wallet = $this->user->createWallet('USD', 'Test Wallet');

    $foundWallet = $this->user->getWalletBySlug($wallet->slug);

    expect($foundWallet->id)->toBe($wallet->id);
});

test('it returns null for nonexistent slug', function () {
    $this->user->createWallet('USD', 'Test Wallet');

    $foundWallet = $this->user->getWalletBySlug('nonexistent-slug');

    expect($foundWallet)->toBeNull();
});

test('it can get wallet or create', function () {
    $wallet = $this->user->getWalletOrCreate('USD', 'USD Wallet');

    expect($wallet)->toBeInstanceOf(Wallet::class);
    expect($wallet->currency)->toBe('USD');

    // Should return the same wallet if called again
    $sameWallet = $this->user->getWalletOrCreate('USD', 'USD Wallet');
    expect($wallet->id)->toBe($sameWallet->id);
});

test('it can get balance for currency', function () {
    $wallet = $this->user->createWallet('USD', 'USD Wallet');
    $wallet->credit(100.00, 'available');

    $balance = $this->user->getBalance('USD', 'available');

    expect($balance)->toBe(100.00);
});

test('it returns zero for nonexistent currency balance', function () {
    $balance = $this->user->getBalance('EUR', 'available');

    expect($balance)->toBe(0.0);
});

test('it can get total balance for currency', function () {
    $wallet = $this->user->createWallet('USD', 'USD Wallet');
    $wallet->credit(100.00, 'available');
    $wallet->credit(50.00, 'pending');

    $totalBalance = $this->user->getTotalBalance('USD');

    expect($totalBalance)->toBe(150.00);
});

test('it returns zero for nonexistent currency total balance', function () {
    $totalBalance = $this->user->getTotalBalance('EUR');

    expect($totalBalance)->toBe(0.0);
});

test('it can credit wallet by currency', function () {
    $wallet = $this->user->createWallet('USD', 'USD Wallet');

    $transaction = $this->user->creditWallet('USD', 100.00, 'available', ['description' => 'Test credit']);

    expect($transaction)->toBeInstanceOf(\HWallet\LaravelMultiWallet\Models\Transaction::class);
    expect($this->user->getBalance('USD', 'available'))->toBe(100.00);
});

test('it can debit wallet by currency', function () {
    $wallet = $this->user->createWallet('USD', 'USD Wallet');
    $wallet->credit(100.00, 'available');

    $transaction = $this->user->debitWallet('USD', 50.00, 'available', ['description' => 'Test debit']);

    expect($transaction)->toBeInstanceOf(\HWallet\LaravelMultiWallet\Models\Transaction::class);
    expect($this->user->getBalance('USD', 'available'))->toBe(50.00);
});

test('it throws exception when debiting nonexistent wallet', function () {
    expect(fn () => $this->user->debitWallet('EUR', 50.00, 'available'))->toThrow(\HWallet\LaravelMultiWallet\Exceptions\WalletNotFoundException::class);
});

test('it can transfer between wallets', function () {
    $fromUser = $this->createUser();
    $toUser = $this->createUser();

    $fromWallet = $fromUser->createWallet('USD', 'From Wallet');
    $toWallet = $toUser->createWallet('USD', 'To Wallet');

    $fromWallet->credit(100.00, 'available');

    $transfer = $fromUser->transfer(50.00, 'USD', $toUser, 'USD', ['description' => 'Test transfer']);

    expect($transfer)->toBeInstanceOf(\HWallet\LaravelMultiWallet\Models\Transfer::class);
    expect($fromUser->getBalance('USD', 'available'))->toBe(50.00);
    expect($toUser->getBalance('USD', 'available'))->toBe(50.00);
});

test('it can get transfers from', function () {
    $fromUser = $this->createUser();
    $toUser = $this->createUser();

    $fromWallet = $fromUser->createWallet('USD', 'From Wallet');
    $toWallet = $toUser->createWallet('USD', 'To Wallet');

    $fromWallet->credit(100.00, 'available');
    $transfer = $fromUser->transfer(50.00, 'USD', $toUser, 'USD');

    $transfers = $fromUser->transfersFrom;

    expect($transfers)->toHaveCount(1);
    expect($transfers->first()->id)->toBe($transfer->id);
});

test('it can get transfers to', function () {
    $fromUser = $this->createUser();
    $toUser = $this->createUser();

    $fromWallet = $fromUser->createWallet('USD', 'From Wallet');
    $toWallet = $toUser->createWallet('USD', 'To Wallet');

    $fromWallet->credit(100.00, 'available');
    $transfer = $fromUser->transfer(50.00, 'USD', $toUser, 'USD');

    $transfers = $toUser->transfersTo;

    expect($transfers)->toHaveCount(1);
    expect($transfers->first()->id)->toBe($transfer->id);
});
