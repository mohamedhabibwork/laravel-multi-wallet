<?php

use HWallet\LaravelMultiWallet\Models\Transaction;
use HWallet\LaravelMultiWallet\Models\Wallet;
use HWallet\LaravelMultiWallet\Tests\Models\User;

beforeEach(function () {
    $this->user = $this->createUser();
    $this->wallet = $this->user->createWallet('USD', 'Test Wallet');
    $this->transaction = Transaction::factory()->create([
        'wallet_id' => $this->wallet->id,
        'payable_type' => User::class,
        'payable_id' => $this->user->id,
    ]);
});

test('it can check if credit', function () {
    $this->transaction->update(['type' => 'credit']);
    expect($this->transaction->isCredit())->toBeTrue();
    expect($this->transaction->isDebit())->toBeFalse();
});

test('it can check if debit', function () {
    $this->transaction->update(['type' => 'debit']);
    expect($this->transaction->isDebit())->toBeTrue();
    expect($this->transaction->isCredit())->toBeFalse();
});

test('it can check if confirmed', function () {
    $this->transaction->update(['confirmed' => true]);
    expect($this->transaction->isConfirmed())->toBeTrue();

    $this->transaction->update(['confirmed' => false]);
    expect($this->transaction->isConfirmed())->toBeFalse();
});

test('it can confirm transaction', function () {
    $this->transaction->update(['confirmed' => false]);

    $result = $this->transaction->confirm();

    expect($result)->toBeTrue();
    expect($this->transaction->fresh()->isConfirmed())->toBeTrue();
});

test('it returns true when already confirmed', function () {
    $this->transaction->update(['confirmed' => true]);

    $result = $this->transaction->confirm();

    expect($result)->toBeTrue();
});

test('it can get signed amount', function () {
    $this->transaction->update(['type' => 'credit', 'amount' => 100.00]);
    expect($this->transaction->getSignedAmount())->toBe(100.00);

    $this->transaction->update(['type' => 'debit', 'amount' => 50.00]);
    expect($this->transaction->getSignedAmount())->toBe(-50.00);
});

test('it can get description from meta', function () {
    $this->transaction->update(['meta' => ['description' => 'Test transaction']]);
    expect($this->transaction->getDescription())->toBe('Test transaction');

    $this->transaction->update(['meta' => []]);
    expect($this->transaction->getDescription())->toBeNull();
});

test('it can access relationships', function () {
    expect($this->transaction->payable)->toBeInstanceOf(User::class);
    expect($this->transaction->payable->id)->toBe($this->user->id);

    expect($this->transaction->wallet)->toBeInstanceOf(Wallet::class);
    expect($this->transaction->wallet->id)->toBe($this->wallet->id);
});

test('it can scope by wallet', function () {
    $wallet2 = $this->user->createWallet('EUR', 'Test Wallet 2');
    $transaction2 = Transaction::factory()->create(['wallet_id' => $wallet2->id]);

    $walletTransactions = Transaction::byWallet($this->wallet)->get();

    expect($walletTransactions)->toHaveCount(1);
    expect($walletTransactions->first()->id)->toBe($this->transaction->id);
});

test('it can scope by type', function () {
    $this->transaction->update(['type' => 'credit']);
    $transaction2 = Transaction::factory()->create(['type' => 'debit']);

    $creditTransactions = Transaction::byType('credit')->get();
    $debitTransactions = Transaction::byType('debit')->get();

    expect($creditTransactions)->toHaveCount(1);
    expect($debitTransactions)->toHaveCount(1);
});

test('it can scope by balance type', function () {
    $this->transaction->update(['balance_type' => 'available']);
    $transaction2 = Transaction::factory()->create(['balance_type' => 'pending']);

    $availableTransactions = Transaction::byBalanceType('available')->get();
    $pendingTransactions = Transaction::byBalanceType('pending')->get();

    expect($availableTransactions)->toHaveCount(1);
    expect($pendingTransactions)->toHaveCount(1);
});

test('it can scope confirmed', function () {
    $this->transaction->update(['confirmed' => true]);
    $transaction2 = Transaction::factory()->create(['confirmed' => false]);

    $confirmedTransactions = Transaction::confirmed()->get();
    $pendingTransactions = Transaction::pending()->get();

    expect($confirmedTransactions)->toHaveCount(1);
    expect($pendingTransactions)->toHaveCount(1);
});

test('it uses correct table name', function () {
    expect($this->transaction->getTable())->toBe('transactions');
});
