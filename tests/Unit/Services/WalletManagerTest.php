<?php

use HWallet\LaravelMultiWallet\Enums\BalanceType;
use HWallet\LaravelMultiWallet\Enums\TransactionType;
use HWallet\LaravelMultiWallet\Enums\TransferStatus;
use HWallet\LaravelMultiWallet\Exceptions\InsufficientFundsException;
use HWallet\LaravelMultiWallet\Models\Transaction;
use HWallet\LaravelMultiWallet\Models\Transfer;
use HWallet\LaravelMultiWallet\Models\Wallet;
use HWallet\LaravelMultiWallet\Services\WalletManager;
use HWallet\LaravelMultiWallet\Tests\Models\User;

beforeEach(function () {
    $this->walletManager = app(WalletManager::class);
    $this->user = $this->createUser();
    $this->wallet = $this->user->createWallet('USD', 'Test Wallet');
});

test('it can create wallet', function () {
    $wallet = $this->walletManager->createWallet(
        $this->user,
        'EUR',
        'Euro Wallet',
        'European currency wallet',
        ['bank_account' => '123456789']
    );

    expect($wallet)->toBeInstanceOf(Wallet::class);
    expect($wallet->currency)->toBe('EUR');
    expect($wallet->name)->toBe('Euro Wallet');
    expect($wallet->description)->toBe('European currency wallet');
    expect($wallet->meta)->toBe(['bank_account' => '123456789']);
    expect($wallet->holder_id)->toBe($this->user->id);
    expect($wallet->holder_type)->toBe(User::class);
});

test('it can find wallet by id', function () {
    $foundWallet = $this->walletManager->findWallet($this->wallet->id);

    expect($foundWallet)->toBeInstanceOf(Wallet::class);
    expect($foundWallet->id)->toBe($this->wallet->id);
});

test('it returns null for nonexistent wallet', function () {
    $foundWallet = $this->walletManager->findWallet(99999);

    expect($foundWallet)->toBeNull();
});

test('it can find wallet by holder and currency', function () {
    $foundWallet = $this->walletManager->findWalletByHolderAndCurrency($this->user, 'USD', 'Test Wallet');

    expect($foundWallet)->toBeInstanceOf(Wallet::class);
    expect($foundWallet->id)->toBe($this->wallet->id);
});

test('it returns null for nonexistent holder currency combination', function () {
    $foundWallet = $this->walletManager->findWalletByHolderAndCurrency($this->user, 'EUR');

    expect($foundWallet)->toBeNull();
});

test('it can get or create wallet', function () {
    // Should return existing wallet
    $wallet1 = $this->walletManager->getOrCreateWallet($this->user, 'USD', 'Test Wallet');
    expect($wallet1->id)->toBe($this->wallet->id);

    // Should create new wallet
    $wallet2 = $this->walletManager->getOrCreateWallet($this->user, 'EUR', 'EUR Wallet');
    expect($wallet2->id)->not->toBe($this->wallet->id);
    expect($wallet2->currency)->toBe('EUR');
});

test('it can credit wallet', function () {
    $transaction = $this->walletManager->creditWallet(
        $this->wallet,
        100.00,
        'available',
        ['description' => 'Test credit']
    );

    expect($transaction)->toBeInstanceOf(Transaction::class);
    expect($transaction->type)->toBe(TransactionType::CREDIT);
    expect((float) $transaction->amount)->toBe(100.00);
    expect($transaction->balance_type)->toBe(BalanceType::AVAILABLE);
    expect($this->wallet->fresh()->getBalance('available'))->toBe(100.00);
});

test('it can debit wallet', function () {
    $this->wallet->credit(100.00, 'available');

    $transaction = $this->walletManager->debitWallet(
        $this->wallet,
        50.00,
        'available',
        ['description' => 'Test debit']
    );

    expect($transaction)->toBeInstanceOf(Transaction::class);
    expect($transaction->type)->toBe(TransactionType::DEBIT);
    expect((float) $transaction->amount)->toBe(50.00);
    expect($this->wallet->fresh()->getBalance('available'))->toBe(50.00);
});

test('it throws exception when debiting insufficient funds', function () {
    $this->wallet->credit(50.00, 'available');

    expect(fn () => $this->walletManager->debitWallet($this->wallet, 100.00, 'available'))->toThrow(InsufficientFundsException::class);
});

test('it can transfer between wallets', function () {
    $toUser = $this->createUser();
    $toWallet = $toUser->createWallet('USD', 'To Wallet');

    $this->wallet->credit(100.00, 'available');

    $transfer = $this->walletManager->transfer(
        $this->wallet,
        $toWallet,
        50.00,
        ['description' => 'Test transfer']
    );

    expect($transfer)->toBeInstanceOf(Transfer::class);
    expect($transfer->status)->toBe(TransferStatus::CONFIRMED);
    expect($this->wallet->fresh()->getBalance('available'))->toBe(50.00);
    expect($toWallet->fresh()->getBalance('available'))->toBe(50.00);
});

test('it can confirm transfer', function () {
    $toUser = $this->createUser();
    $toWallet = $toUser->createWallet('USD', 'To Wallet');

    $this->wallet->credit(100.00, 'available');
    $transfer = $this->walletManager->transfer($this->wallet, $toWallet, 50.00);

    $result = $this->walletManager->confirmTransfer($transfer);

    expect($result)->toBeTrue();
    expect($transfer->fresh()->status)->toBe(TransferStatus::CONFIRMED);
});

test('it can reject transfer', function () {
    $toUser = $this->createUser();
    $toWallet = $toUser->createWallet('USD', 'To Wallet');

    $this->wallet->credit(100.00, 'available');
    $transfer = $this->walletManager->transfer($this->wallet, $toWallet, 50.00, ['status' => TransferStatus::PENDING]);

    $result = $this->walletManager->rejectTransfer($transfer, 'Insufficient funds');

    expect($result)->toBeTrue();
    expect($transfer->fresh()->status)->toBe(TransferStatus::REJECTED);
    expect($this->wallet->fresh()->getBalance('available'))->toBe(100.00);
    expect($toWallet->fresh()->getBalance('available'))->toBe(0.0);
});

test('it can get wallet balance', function () {
    $this->wallet->credit(100.00, 'available');
    $this->wallet->credit(50.00, 'pending');

    $availableBalance = $this->walletManager->getWalletBalance($this->wallet, 'available');
    $pendingBalance = $this->walletManager->getWalletBalance($this->wallet, 'pending');
    $totalBalance = $this->walletManager->getWalletBalance($this->wallet);

    expect($availableBalance)->toBe(100.00);
    expect($pendingBalance)->toBe(50.00);
    expect($totalBalance)->toBe(150.00);
});

test('it can get wallet history', function () {
    $this->wallet->credit(100.00, 'available');
    $this->wallet->debit(50.00, 'available');

    $history = $this->walletManager->getWalletHistory($this->wallet);

    expect($history)->toHaveCount(2);
    expect($history->first())->toBeInstanceOf(Transaction::class);
});

test('it can get wallet transactions', function () {
    $this->wallet->credit(100.00, 'available', ['description' => 'Credit 1']);
    $this->wallet->credit(50.00, 'available', ['description' => 'Credit 2']);

    $transactions = $this->walletManager->getWalletTransactions($this->wallet);

    expect($transactions)->toHaveCount(2);
    expect($transactions->first())->toBeInstanceOf(Transaction::class);
});

test('it can get wallet transfers', function () {
    $toUser = $this->createUser();
    $toWallet = $toUser->createWallet('USD', 'To Wallet');

    $this->wallet->credit(100.00, 'available');
    $this->walletManager->transfer($this->wallet, $toWallet, 50.00);

    $outgoingTransfers = $this->walletManager->getWalletTransfers($this->wallet, 'outgoing');
    $incomingTransfers = $this->walletManager->getWalletTransfers($toWallet, 'incoming');

    expect($outgoingTransfers)->toHaveCount(1);
    expect($incomingTransfers)->toHaveCount(1);
    expect($outgoingTransfers->first())->toBeInstanceOf(Transfer::class);
});

test('it can delete wallet', function () {
    $walletId = $this->wallet->id;
    $result = $this->walletManager->deleteWallet($this->wallet);

    expect($result)->toBeTrue();
    expect(Wallet::find($walletId))->toBeNull();
});

test('it can freeze wallet', function () {
    $this->wallet->credit(100.00, 'available');

    $result = $this->walletManager->freezeWallet($this->wallet, 50.00, 'Security check');

    expect($result)->toBeTrue();
    expect($this->wallet->fresh()->getBalance('available'))->toBe(50.00);
    expect($this->wallet->fresh()->getBalance('frozen'))->toBe(50.00);
});

test('it can unfreeze wallet', function () {
    $this->wallet->credit(100.00, 'available');
    $this->walletManager->freezeWallet($this->wallet, 50.00, 'Security check');

    $result = $this->walletManager->unfreezeWallet($this->wallet, 50.00, 'Security cleared');

    expect($result)->toBeTrue();
    expect($this->wallet->fresh()->getBalance('available'))->toBe(100.00);
    expect($this->wallet->fresh()->getBalance('frozen'))->toBe(0.0);
});
