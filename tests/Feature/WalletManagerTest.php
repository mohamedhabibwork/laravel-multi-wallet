<?php

use HWallet\LaravelMultiWallet\Enums\BalanceType;
use HWallet\LaravelMultiWallet\Enums\TransactionType;
use HWallet\LaravelMultiWallet\Exceptions\InsufficientFundsException;
use HWallet\LaravelMultiWallet\Models\Wallet;
use HWallet\LaravelMultiWallet\Services\WalletManager;
use HWallet\LaravelMultiWallet\Tests\Models\User;

beforeEach(function () {
    $this->walletManager = app(WalletManager::class);
    $this->user = User::factory()->create();
});

test('it can create a wallet', function () {
    $wallet = $this->walletManager->create($this->user, 'USD', 'Main Wallet');

    expect($wallet)->toBeInstanceOf(Wallet::class);
    expect($wallet->currency)->toBe('USD');
    expect($wallet->name)->toBe('Main Wallet');
    expect($wallet->getBalance(BalanceType::AVAILABLE))->toBe(0.0);
    expect($wallet->slug)->not->toBeEmpty();
});

test('it can credit a wallet', function () {
    $wallet = $this->walletManager->create($this->user, 'USD');

    $transaction = $wallet->credit(100.50, BalanceType::AVAILABLE, ['description' => 'Initial deposit']);

    expect($wallet->fresh()->getBalance(BalanceType::AVAILABLE))->toBe(100.50);
    expect($transaction->type)->toBe(TransactionType::CREDIT);
    expect($transaction->amount)->toBe('100.50000000');
    expect($transaction->isConfirmed())->toBeTrue();
});

test('it can debit a wallet', function () {
    $wallet = $this->walletManager->create($this->user, 'USD');
    $wallet->credit(100, BalanceType::AVAILABLE);

    $transaction = $wallet->debit(50, BalanceType::AVAILABLE, ['description' => 'Purchase']);

    expect($wallet->fresh()->getBalance(BalanceType::AVAILABLE))->toBe(50.0);
    expect($transaction->type)->toBe(TransactionType::DEBIT);
    expect($transaction->amount)->toBe('50.00000000');
});

test('it throws exception when debiting insufficient funds', function () {
    $wallet = $this->walletManager->create($this->user, 'USD');
    $wallet->credit(50, BalanceType::AVAILABLE);

    expect(fn () => $wallet->debit(100, BalanceType::AVAILABLE))->toThrow(InsufficientFundsException::class);
});

test('it can transfer between wallets', function () {
    $user1 = $this->user;
    $user2 = User::factory()->create();

    $wallet1 = $this->walletManager->create($user1, 'USD');
    $wallet2 = $this->walletManager->create($user2, 'USD');

    $wallet1->credit(200, BalanceType::AVAILABLE);

    $transfer = $this->walletManager->transfer($wallet1, $wallet2, 100, [
        'description' => 'Payment transfer',
    ]);

    expect($wallet1->fresh()->getBalance(BalanceType::AVAILABLE))->toBe(100.0);
    expect($wallet2->fresh()->getBalance(BalanceType::AVAILABLE))->toBe(100.0);
    expect($transfer->withdraw)->not->toBeNull();
    expect($transfer->deposit)->not->toBeNull();
    expect($transfer->isConfirmed())->toBeTrue();
});

test('it can transfer with fee', function () {
    $user1 = $this->user;
    $user2 = User::factory()->create();

    $wallet1 = $this->walletManager->create($user1, 'USD');
    $wallet2 = $this->walletManager->create($user2, 'USD');

    $wallet1->credit(200, BalanceType::AVAILABLE);

    $transfer = $this->walletManager->transferWithFee($wallet1, $wallet2, 100, 5, 'Transfer with fee');

    expect($wallet1->fresh()->getBalance(BalanceType::AVAILABLE))->toBe(95.0); // 200 - 100 - 5 (fee)
    expect($wallet2->fresh()->getBalance(BalanceType::AVAILABLE))->toBe(100.0);
    expect($transfer->fee)->toBe('5.00000000');
});

test('it can manage pending balance', function () {
    $wallet = $this->walletManager->create($this->user, 'USD');
    $wallet->credit(100, BalanceType::AVAILABLE);

    // Move to pending
    $wallet->moveToPending(50, 'Processing payment');

    expect($wallet->fresh()->getBalance(BalanceType::AVAILABLE))->toBe(50.0);
    expect($wallet->fresh()->getBalance(BalanceType::PENDING))->toBe(50.0);

    // Confirm pending
    $wallet->confirmPending(30, 'Payment confirmed');

    expect($wallet->fresh()->getBalance(BalanceType::AVAILABLE))->toBe(80.0);
    expect($wallet->fresh()->getBalance(BalanceType::PENDING))->toBe(20.0);
});

test('it can freeze and unfreeze funds', function () {
    $wallet = $this->walletManager->create($this->user, 'USD');
    $wallet->credit(100, BalanceType::AVAILABLE);

    // Freeze funds
    $wallet->freeze(30, 'Security hold');

    expect($wallet->fresh()->getBalance(BalanceType::AVAILABLE))->toBe(70.0);
    expect($wallet->fresh()->getBalance(BalanceType::FROZEN))->toBe(30.0);

    // Unfreeze funds
    $wallet->unfreeze(30, 'Security cleared');

    expect($wallet->fresh()->getBalance(BalanceType::AVAILABLE))->toBe(100.0);
    expect($wallet->fresh()->getBalance(BalanceType::FROZEN))->toBe(0.0);
});

test('it can manage trial balance', function () {
    $wallet = $this->walletManager->create($this->user, 'USD');

    // Add trial balance
    $wallet->addTrialBalance(50, 'Trial credit');

    expect($wallet->fresh()->getBalance(BalanceType::TRIAL))->toBe(50.0);
    expect($wallet->fresh()->getBalance(BalanceType::AVAILABLE))->toBe(0.0);

    // Convert trial to available
    $wallet->convertTrialToAvailable(25, 'Trial approved');

    expect($wallet->fresh()->getBalance(BalanceType::TRIAL))->toBe(25.0);
    expect($wallet->fresh()->getBalance(BalanceType::AVAILABLE))->toBe(25.0);
});

test('it can get balance summary', function () {
    $wallet1 = $this->walletManager->create($this->user, 'USD', 'Wallet 1');
    $wallet2 = $this->walletManager->create($this->user, 'EUR', 'Wallet 2');

    $wallet1->credit(100, BalanceType::AVAILABLE);
    $wallet2->credit(200, BalanceType::AVAILABLE);

    $summary = $this->walletManager->getBalanceSummary($this->user);

    expect($summary)->toHaveCount(2);

    $usdSummary = collect($summary)->firstWhere('currency', 'USD');
    $eurSummary = collect($summary)->firstWhere('currency', 'EUR');

    expect($usdSummary['available_balance'])->toBe(100.0);
    expect($eurSummary['available_balance'])->toBe(200.0);
});

test('it enforces uniqueness when enabled', function () {
    $wallet1 = $this->walletManager->create($this->user, 'USD', 'Main');

    expect(fn () => $this->walletManager->create($this->user, 'USD', 'Main'))->toThrow(\InvalidArgumentException::class, 'Wallet already exists for this currency and name combination');
});

test('it can get or create wallet', function () {
    // First call creates wallet
    $wallet1 = $this->walletManager->getOrCreate($this->user, 'USD', 'Main');

    expect($wallet1)->toBeInstanceOf(Wallet::class);
    expect($wallet1->currency)->toBe('USD');
    expect($wallet1->name)->toBe('Main');

    // Second call returns existing wallet
    $wallet2 = $this->walletManager->getOrCreate($this->user, 'USD', 'Main');

    expect($wallet1->id)->toBe($wallet2->id);
});
