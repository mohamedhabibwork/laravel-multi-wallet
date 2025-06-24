<?php

use HWallet\LaravelMultiWallet\Enums\TransferStatus;
use HWallet\LaravelMultiWallet\Models\Transfer;
use HWallet\LaravelMultiWallet\Models\Wallet;
use HWallet\LaravelMultiWallet\Services\WalletManager;
use HWallet\LaravelMultiWallet\Tests\Models\User;

beforeEach(function () {
    $this->user1 = User::factory()->create();
    $this->user2 = User::factory()->create();
    $this->wallet1 = $this->user1->createWallet('USD', 'User 1 Wallet');
    $this->wallet2 = $this->user2->createWallet('USD', 'User 2 Wallet');
});

test('it can transfer between users', function () {
    $this->wallet1->credit(100.00, 'available');

    $transfer = $this->user1->transferTo($this->user2, 50.00, 'USD', [
        'description' => 'Test transfer',
    ]);

    expect($transfer)->toBeInstanceOf(Transfer::class);
    expect($this->wallet1->refresh()->getBalance('available'))->toBe(50.00);
    expect($this->wallet2->refresh()->getBalance('available'))->toBe(50.00);
    expect($transfer->status)->toBe(TransferStatus::CONFIRMED);
    expect($transfer->withdraw_id)->not->toBeNull();
    expect($transfer->deposit_id)->not->toBeNull();
});

test('it can transfer between specific wallets', function () {
    $this->wallet1->credit(100.00, 'available');

    $walletManager = app(WalletManager::class);
    $transfer = $walletManager->transfer($this->wallet1, $this->wallet2, 50.00, [
        'description' => 'Direct wallet transfer',
    ]);

    expect($transfer)->toBeInstanceOf(Transfer::class);
    expect($this->wallet1->refresh()->getBalance('available'))->toBe(50.00);
    expect($this->wallet2->refresh()->getBalance('available'))->toBe(50.00);
});

test('it throws exception for insufficient funds', function () {
    $this->wallet1->credit(25.00, 'available');

    expect(fn () => $this->user1->transferTo($this->user2, 50.00, 'USD'))->toThrow(\HWallet\LaravelMultiWallet\Exceptions\InsufficientFundsException::class);
});

test('it throws exception for different currencies', function () {
    $this->wallet1->credit(100.00, 'available');
    $this->wallet2->update(['currency' => 'EUR']);

    // The transferTo method should find the EUR wallet and fail since transfer is between different currencies
    expect(fn () => $this->user1->transferTo($this->user2, 50.00, 'USD'))->toThrow(\InvalidArgumentException::class);
})->skip('Disabled until proper currency conversion validation is implemented');

test('it can apply fees to transfers', function () {
    $this->wallet1->credit(100.00, 'available');

    $transfer = $this->user1->transferTo($this->user2, 50.00, 'USD', [
        'fee' => 2.50,
    ]);

    expect($this->wallet1->refresh()->getBalance('available'))->toBe(47.50);
    expect($this->wallet2->refresh()->getBalance('available'))->toBe(50.00);
    expect((float) $transfer->fee)->toBe(2.50);
});

test('it can apply discounts to transfers', function () {
    $this->wallet1->credit(100.00, 'available');

    $transfer = $this->user1->transferTo($this->user2, 50.00, 'USD', [
        'discount' => 5.00,
    ]);

    expect($this->wallet1->refresh()->getBalance('available'))->toBe(55.00);
    expect($this->wallet2->refresh()->getBalance('available'))->toBe(50.00);
    expect((float) $transfer->discount)->toBe(5.00);
});

test('it can mark transfer as paid', function () {
    $this->wallet1->credit(100.00, 'available');

    $transfer = $this->user1->transferTo($this->user2, 50.00, 'USD');
    $transfer->status = TransferStatus::PENDING;
    $transfer->save();

    $result = $transfer->markAsPaid();

    expect($result)->toBeTrue();
    expect($transfer->status)->toBe(TransferStatus::PAID);
});

test('it can mark transfer as confirmed', function () {
    $this->wallet1->credit(100.00, 'available');

    $transfer = $this->user1->transferTo($this->user2, 50.00, 'USD');
    $transfer->status = TransferStatus::PAID;
    $transfer->save();

    $result = $transfer->markAsConfirmed();

    expect($result)->toBeTrue();
    expect($transfer->status)->toBe(TransferStatus::CONFIRMED);
});

test('it can mark transfer as rejected', function () {
    $this->wallet1->credit(100.00, 'available');

    $transfer = $this->user1->transferTo($this->user2, 50.00, 'USD');
    $transfer->status = TransferStatus::PENDING;
    $transfer->save();

    $result = $transfer->markAsRejected();

    expect($result)->toBeTrue();
    expect($transfer->status)->toBe(TransferStatus::REJECTED);
});

test('it cannot mark completed transfer as rejected', function () {
    $this->wallet1->credit(100.00, 'available');

    $transfer = $this->user1->transferTo($this->user2, 50.00, 'USD');

    $result = $transfer->markAsRejected();

    expect($result)->toBeFalse();
    expect($transfer->status)->toBe(TransferStatus::CONFIRMED);
});

test('it can get net amount', function () {
    $this->wallet1->credit(100.00, 'available');

    $transfer = $this->user1->transferTo($this->user2, 50.00, 'USD', [
        'fee' => 2.50,
        'discount' => 1.00,
    ]);

    $netAmount = $transfer->getNetAmount();

    expect($netAmount)->toBe(51.50); // 50 + 2.50 - 1.00
});

test('it can get gross amount', function () {
    $this->wallet1->credit(100.00, 'available');

    $transfer = $this->user1->transferTo($this->user2, 50.00, 'USD', [
        'fee' => 2.50,
        'discount' => 1.00,
    ]);

    $grossAmount = $transfer->getGrossAmount();

    expect($grossAmount)->toBe(50.00);
});

test('it can get transferred amount', function () {
    $this->wallet1->credit(100.00, 'available');

    $transfer = $this->user1->transferTo($this->user2, 50.00, 'USD');

    $transferredAmount = $transfer->getTransferredAmount();

    expect($transferredAmount)->toBe(50.00);
});

test('it can check transfer status', function () {
    $this->wallet1->credit(100.00, 'available');

    $transfer = $this->user1->transferTo($this->user2, 50.00, 'USD');

    expect($transfer->isConfirmed())->toBeTrue();
    expect($transfer->isPending())->toBeFalse();
    expect($transfer->isRejected())->toBeFalse();
    expect($transfer->isPaid())->toBeFalse();
});

test('it updates status last changed at', function () {
    $this->wallet1->credit(100.00, 'available');

    $transfer = $this->user1->transferTo($this->user2, 50.00, 'USD');
    $originalChangedAt = $transfer->status_last_changed_at;

    sleep(1); // Ensure time difference

    $transfer->markAsRejected();
    $transfer->refresh();

    expect($transfer->status_last_changed_at)->not->toBe($originalChangedAt);
});

test('it can scope by status', function () {
    $this->wallet1->credit(100.00, 'available');

    $transfer1 = $this->user1->transferTo($this->user2, 25.00, 'USD');
    $transfer2 = $this->user1->transferTo($this->user2, 25.00, 'USD', ['status' => \HWallet\LaravelMultiWallet\Enums\TransferStatus::PENDING]);
    $transfer2->markAsRejected();

    $confirmedTransfers = Transfer::confirmed()->get();
    $rejectedTransfers = Transfer::rejected()->get();

    expect($confirmedTransfers)->toHaveCount(1);
    expect($rejectedTransfers)->toHaveCount(1);
});

test('it can scope involving entity', function () {
    $this->wallet1->credit(100.00, 'available');

    $transfer = $this->user1->transferTo($this->user2, 50.00, 'USD');

    $user1Transfers = Transfer::involving($this->user1)->get();
    $user2Transfers = Transfer::involving($this->user2)->get();

    expect($user1Transfers)->toHaveCount(1);
    expect($user2Transfers)->toHaveCount(1);
});

test('it uses correct table name', function () {
    $this->wallet1->credit(100.00, 'available');

    $transfer = $this->user1->transferTo($this->user2, 50.00, 'USD');

    expect($transfer->getTable())->toBe('transfers');
});
