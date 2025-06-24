<?php

use HWallet\LaravelMultiWallet\Exceptions\InsufficientFundsException;
use HWallet\LaravelMultiWallet\Exceptions\InvalidBalanceTypeException;
use HWallet\LaravelMultiWallet\Models\Transaction;
use HWallet\LaravelMultiWallet\Tests\Models\User;

beforeEach(function () {
    $this->user = $this->createUser();
    $this->wallet = $this->user->createWallet('USD', 'Test Wallet');
});

test('it can get balance for different types', function () {
    $this->wallet->update([
        'balance_pending' => 10.00,
        'balance_available' => 20.00,
        'balance_frozen' => 5.00,
        'balance_trial' => 2.50,
    ]);

    expect($this->wallet->getBalance('pending'))->toBe(10.00);
    expect($this->wallet->getBalance('available'))->toBe(20.00);
    expect($this->wallet->getBalance('frozen'))->toBe(5.00);
    expect($this->wallet->getBalance('trial'))->toBe(2.50);
});

test('it can get total balance', function () {
    $this->wallet->update([
        'balance_pending' => 10.00,
        'balance_available' => 20.00,
        'balance_frozen' => 5.00,
        'balance_trial' => 2.50,
    ]);

    expect($this->wallet->getTotalBalance())->toBe(37.50);
});

test('it can credit wallet', function () {
    $transaction = $this->wallet->credit(100.00, 'available', ['description' => 'Test credit']);

    expect($transaction)->toBeInstanceOf(Transaction::class);
    expect($this->wallet->fresh()->getBalance('available'))->toBe(100.00);
    expect($transaction->type)->toBe(\HWallet\LaravelMultiWallet\Enums\TransactionType::CREDIT);
    expect((float) $transaction->amount)->toBe(100.00);
    expect($transaction->getDescription())->toBe('Test credit');
});

test('it can debit wallet', function () {
    $this->wallet->credit(100.00, 'available');

    $transaction = $this->wallet->debit(50.00, 'available', ['description' => 'Test debit']);

    expect($transaction)->toBeInstanceOf(Transaction::class);
    expect($this->wallet->fresh()->getBalance('available'))->toBe(50.00);
    expect($transaction->type)->toBe(\HWallet\LaravelMultiWallet\Enums\TransactionType::DEBIT);
    expect((float) $transaction->amount)->toBe(50.00);
});

test('it throws exception when debiting insufficient funds', function () {
    $this->wallet->credit(50.00, 'available');

    expect(fn () => $this->wallet->debit(100.00, 'available'))->toThrow(InsufficientFundsException::class);
});

test('it throws exception for negative amounts', function () {
    expect(fn () => $this->wallet->credit(-100.00, 'available'))->toThrow(\InvalidArgumentException::class);
});

test('it validates balance type', function () {
    expect(fn () => $this->wallet->credit(100.00, 'invalid_type'))->toThrow(InvalidBalanceTypeException::class);
});

test('it can check if can debit', function () {
    $this->wallet->credit(100.00, 'available');

    expect($this->wallet->canDebit(50.00, 'available'))->toBeTrue();
    expect($this->wallet->canDebit(150.00, 'available'))->toBeFalse();
});

test('it can move amount to pending', function () {
    $this->wallet->credit(100.00, 'available');

    $transaction = $this->wallet->moveToPending(30.00, 'Processing payment');

    expect($this->wallet->fresh()->getBalance('available'))->toBe(70.00);
    expect($this->wallet->fresh()->getBalance('pending'))->toBe(30.00);
    expect($transaction->meta['moved_to_pending'] ?? false)->toBeTrue();
});

test('it can confirm pending amount', function () {
    $this->wallet->credit(100.00, 'available');
    $this->wallet->moveToPending(30.00);

    $result = $this->wallet->confirmPending(30.00, 'Payment confirmed');

    expect($result)->toBeTrue();
    expect($this->wallet->fresh()->getBalance('available'))->toBe(100.00);
    expect($this->wallet->fresh()->getBalance('pending'))->toBe(0.0);
});

test('it can freeze amount', function () {
    $this->wallet->credit(100.00, 'available');

    $transaction = $this->wallet->freeze(25.00, 'Security hold');

    expect($this->wallet->fresh()->getBalance('available'))->toBe(75.00);
    expect($this->wallet->fresh()->getBalance('frozen'))->toBe(25.00);
    expect($transaction->meta['frozen'] ?? false)->toBeTrue();
});

test('it can unfreeze amount', function () {
    $this->wallet->credit(100.00, 'available');
    $this->wallet->freeze(25.00);

    $transaction = $this->wallet->unfreeze(25.00, 'Security cleared');

    expect($this->wallet->fresh()->getBalance('available'))->toBe(100.00);
    expect($this->wallet->fresh()->getBalance('frozen'))->toBe(0.0);
    expect($transaction->meta['unfrozen'] ?? false)->toBeTrue();
});

test('it can add trial balance', function () {
    $transaction = $this->wallet->addTrialBalance(50.00, 'Trial credit');

    expect($this->wallet->fresh()->getBalance('trial'))->toBe(50.00);
    expect($transaction->meta['trial_balance'] ?? false)->toBeTrue();
});

test('it can convert trial to available', function () {
    $this->wallet->addTrialBalance(50.00);

    $result = $this->wallet->convertTrialToAvailable(50.00, 'Trial approved');

    expect($result)->toBeTrue();
    expect($this->wallet->fresh()->getBalance('available'))->toBe(50.00);
    expect($this->wallet->fresh()->getBalance('trial'))->toBe(0.0);
});

test('it generates unique slugs', function () {
    // Create wallets with the same name slug pattern to test slug uniqueness
    $wallet1 = $this->user->createWallet('USD', 'Test Wallet Slug');
    $wallet2 = $this->user->createWallet('EUR', 'Test Wallet Slug');

    expect($wallet1->slug)->not->toBe($wallet2->slug);
    expect($wallet1->slug)->toStartWith('test-wallet-slug');
    expect($wallet2->slug)->toStartWith('test-wallet-slug');
});

test('it can access relationships', function () {
    $this->wallet->credit(100.00, 'available');

    expect($this->wallet->holder)->toBeInstanceOf(User::class);
    expect($this->wallet->holder->id)->toBe($this->user->id);
    expect($this->wallet->transactions)->toHaveCount(1);
    expect($this->wallet->transactions->first())->toBeInstanceOf(Transaction::class);
});
