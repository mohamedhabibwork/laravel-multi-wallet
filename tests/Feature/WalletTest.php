<?php

use HWallet\LaravelMultiWallet\Enums\TransactionType;
use HWallet\LaravelMultiWallet\Exceptions\InsufficientFundsException;
use HWallet\LaravelMultiWallet\Exceptions\InvalidBalanceTypeException;
use HWallet\LaravelMultiWallet\Models\Transaction;
use HWallet\LaravelMultiWallet\Models\Wallet;
use HWallet\LaravelMultiWallet\Tests\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('it can create a wallet', function () {
    $wallet = $this->user->createWallet('USD', 'Test Wallet');

    expect($wallet)->toBeInstanceOf(Wallet::class);
    expect($wallet->currency)->toBe('USD');
    expect($wallet->name)->toBe('Test Wallet');
    expect($wallet->holder_id)->toBe($this->user->id);
    expect($wallet->holder_type)->toBe(User::class);
    expect($wallet->getBalance('available'))->toBe(0.0);
});

test('it can credit a wallet', function () {
    $wallet = $this->user->createWallet('USD', 'Test Wallet');

    $transaction = $wallet->credit(100.50, 'available', ['description' => 'Test credit']);

    expect($transaction)->toBeInstanceOf(Transaction::class);
    expect($wallet->getBalance('available'))->toBe(100.50);
    expect($transaction->type)->toBe(TransactionType::CREDIT);
    expect((float) $transaction->amount)->toBe(100.50);
    expect($transaction->getDescription())->toBe('Test credit');
});

test('it can debit a wallet', function () {
    $wallet = $this->user->createWallet('USD', 'Test Wallet');
    $wallet->credit(100.00, 'available');

    $transaction = $wallet->debit(50.00, 'available', ['description' => 'Test debit']);

    expect($transaction)->toBeInstanceOf(Transaction::class);
    expect($wallet->getBalance('available'))->toBe(50.00);
    expect($transaction->type)->toBe(TransactionType::DEBIT);
    expect((float) $transaction->amount)->toBe(50.00);
});

test('it throws exception when debiting insufficient funds', function () {
    $wallet = $this->user->createWallet('USD', 'Test Wallet');
    $wallet->credit(50.00, 'available');

    expect(fn () => $wallet->debit(100.00, 'available'))->toThrow(InsufficientFundsException::class);
});

test('it can move amount to pending', function () {
    $wallet = $this->user->createWallet('USD', 'Test Wallet');
    $wallet->credit(100.00, 'available');

    $transaction = $wallet->moveToPending(30.00, 'Processing payment');

    expect($wallet->getBalance('available'))->toBe(70.00);
    expect($wallet->getBalance('pending'))->toBe(30.00);
    expect($transaction->meta['moved_to_pending'] ?? false)->toBeTrue();
});

test('it can confirm pending amount', function () {
    $wallet = $this->user->createWallet('USD', 'Test Wallet');
    $wallet->credit(100.00, 'available');
    $wallet->moveToPending(30.00);

    $result = $wallet->confirmPending(30.00, 'Payment confirmed');

    expect($result)->toBeTrue();
    expect($wallet->getBalance('available'))->toBe(100.00);
    expect($wallet->getBalance('pending'))->toBe(0.0);
});

test('it can freeze amount', function () {
    $wallet = $this->user->createWallet('USD', 'Test Wallet');
    $wallet->credit(100.00, 'available');

    $transaction = $wallet->freeze(25.00, 'Security hold');

    expect($wallet->getBalance('available'))->toBe(75.00);
    expect($wallet->getBalance('frozen'))->toBe(25.00);
    expect($transaction->meta['frozen'] ?? false)->toBeTrue();
});

test('it can unfreeze amount', function () {
    $wallet = $this->user->createWallet('USD', 'Test Wallet');
    $wallet->credit(100.00, 'available');
    $wallet->freeze(25.00);

    $transaction = $wallet->unfreeze(25.00, 'Security cleared');

    expect($wallet->getBalance('available'))->toBe(100.00);
    expect($wallet->getBalance('frozen'))->toBe(0.0);
    expect($transaction->meta['unfrozen'] ?? false)->toBeTrue();
});

test('it can add trial balance', function () {
    $wallet = $this->user->createWallet('USD', 'Test Wallet');

    $transaction = $wallet->addTrialBalance(50.00, 'Trial credit');

    expect($wallet->getBalance('trial'))->toBe(50.00);
    expect($transaction->meta['trial_balance'] ?? false)->toBeTrue();
});

test('it can convert trial to available', function () {
    $wallet = $this->user->createWallet('USD', 'Test Wallet');
    $wallet->addTrialBalance(50.00);

    $result = $wallet->convertTrialToAvailable(50.00, 'Trial approved');

    expect($result)->toBeTrue();
    expect($wallet->getBalance('available'))->toBe(50.00);
    expect($wallet->getBalance('trial'))->toBe(0.0);
});

test('it can get total balance', function () {
    $wallet = $this->user->createWallet('USD', 'Test Wallet');
    $wallet->credit(100.00, 'available');
    $wallet->credit(50.00, 'pending');
    $wallet->credit(25.00, 'frozen');
    $wallet->credit(10.00, 'trial');

    $total = $wallet->getTotalBalance();

    expect($total)->toBe(185.00);
});

test('it validates balance type', function () {
    $wallet = $this->user->createWallet('USD', 'Test Wallet');

    expect(fn () => $wallet->credit(100.00, 'invalid_type'))->toThrow(InvalidBalanceTypeException::class);
});

test('it throws exception for negative amounts', function () {
    $wallet = $this->user->createWallet('USD', 'Test Wallet');

    expect(fn () => $wallet->credit(-100.00, 'available'))->toThrow(\InvalidArgumentException::class);
});

test('it can check if can debit', function () {
    $wallet = $this->user->createWallet('USD', 'Test Wallet');
    $wallet->credit(100.00, 'available');

    expect($wallet->canDebit(50.00, 'available'))->toBeTrue();
    expect($wallet->canDebit(150.00, 'available'))->toBeFalse();
});

test('it generates unique slugs', function () {
    // Temporarily disable uniqueness to test slug generation
    config(['multi-wallet.uniqueness_enabled' => false]);

    $wallet1 = $this->user->createWallet('USD', 'Test Wallet');
    $wallet2 = $this->user->createWallet('USD', 'Test Wallet');

    expect($wallet1->slug)->not->toBe($wallet2->slug);
    expect($wallet1->slug)->toStartWith('test-wallet');
    expect($wallet2->slug)->toStartWith('test-wallet');
});

test('it can access relationships', function () {
    $wallet = $this->user->createWallet('USD', 'Test Wallet');
    $wallet->credit(100.00, 'available');

    expect($wallet->holder)->toBeInstanceOf(User::class);
    expect($wallet->holder->id)->toBe($this->user->id);
    expect($wallet->transactions)->toHaveCount(1);
    expect($wallet->transactions->first())->toBeInstanceOf(Transaction::class);
});

test('it uses correct table name', function () {
    $wallet = $this->user->createWallet('USD', 'Test Wallet');

    expect($wallet->getTable())->toBe('wallets');
});
