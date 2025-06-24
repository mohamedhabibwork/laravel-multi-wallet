<?php

use HWallet\LaravelMultiWallet\Models\Transaction;
use HWallet\LaravelMultiWallet\Models\Transfer;
use HWallet\LaravelMultiWallet\Tests\Models\User;

beforeEach(function () {
    $this->fromUser = $this->createUser();
    $this->toUser = $this->createUser();
    $this->transfer = Transfer::factory()->create([
        'from_type' => User::class,
        'from_id' => $this->fromUser->id,
        'to_type' => User::class,
        'to_id' => $this->toUser->id,
    ]);
});

test('it can check status', function () {
    $this->transfer->update(['status' => 'pending']);
    expect($this->transfer->isPending())->toBeTrue();
    expect($this->transfer->isPaid())->toBeFalse();

    $this->transfer->update(['status' => 'paid']);
    expect($this->transfer->isPaid())->toBeTrue();
    expect($this->transfer->isPending())->toBeFalse();

    $this->transfer->update(['status' => 'confirmed']);
    expect($this->transfer->isConfirmed())->toBeTrue();

    $this->transfer->update(['status' => 'rejected']);
    expect($this->transfer->isRejected())->toBeTrue();
});

test('it can get amount', function () {
    $deposit = Transaction::factory()->create(['amount' => 100.00]);
    $withdraw = Transaction::factory()->create(['amount' => 100.00]);

    $this->transfer->update([
        'deposit_id' => $deposit->id,
        'withdraw_id' => $withdraw->id,
    ]);

    expect($this->transfer->getAmount())->toBe(100.00);
});

test('it can get fee', function () {
    $this->transfer->update(['fee' => 5.00]);
    expect($this->transfer->getFee())->toBe(5.00);

    $this->transfer->update(['fee' => 0.00]);
    expect($this->transfer->getFee())->toBe(0.0);
});

test('it can get discount', function () {
    $this->transfer->update(['discount' => 2.50]);
    expect($this->transfer->getDiscount())->toBe(2.50);

    $this->transfer->update(['discount' => 0.00]);
    expect($this->transfer->getDiscount())->toBe(0.0);
});

test('it can get net amount', function () {
    $deposit = Transaction::factory()->create(['amount' => 100.00]);
    $withdraw = Transaction::factory()->create(['amount' => 100.00]);

    $this->transfer->update([
        'deposit_id' => $deposit->id,
        'withdraw_id' => $withdraw->id,
        'fee' => 5.00,
        'discount' => 2.50,
    ]);

    expect($this->transfer->getNetAmount())->toBe(102.50);
});

test('it can access relationships', function () {
    expect($this->transfer->from)->toBeInstanceOf(User::class);
    expect($this->transfer->from->id)->toBe($this->fromUser->id);

    expect($this->transfer->to)->toBeInstanceOf(User::class);
    expect($this->transfer->to->id)->toBe($this->toUser->id);
});

test('it can scope by status', function () {
    $this->transfer->update(['status' => 'pending']);
    $transfer2 = Transfer::factory()->create(['status' => 'paid']);

    $pendingTransfers = Transfer::byStatus('pending')->get();
    $paidTransfers = Transfer::byStatus('paid')->get();

    expect($pendingTransfers)->toHaveCount(1);
    expect($paidTransfers)->toHaveCount(1);
});

test('it can scope by from', function () {
    $transfer2 = Transfer::factory()->create([
        'from_type' => User::class,
        'from_id' => $this->toUser->id,
    ]);

    $fromTransfers = Transfer::byFrom($this->fromUser)->get();

    expect($fromTransfers)->toHaveCount(1);
    expect($fromTransfers->first()->id)->toBe($this->transfer->id);
});

test('it can scope by to', function () {
    $transfer2 = Transfer::factory()->create([
        'to_type' => User::class,
        'to_id' => $this->fromUser->id,
    ]);

    $toTransfers = Transfer::byTo($this->toUser)->get();

    expect($toTransfers)->toHaveCount(1);
    expect($toTransfers->first()->id)->toBe($this->transfer->id);
});

test('it uses correct table name', function () {
    expect($this->transfer->getTable())->toBe('transfers');
});

test('it can get status changed at', function () {
    $this->transfer->update(['status_last_changed_at' => now()]);

    expect($this->transfer->getStatusChangedAt())->toBeInstanceOf(\Carbon\Carbon::class);
});

test('it can get deposit transaction', function () {
    $deposit = Transaction::factory()->create();
    $this->transfer->update(['deposit_id' => $deposit->id]);

    expect($this->transfer->deposit)->toBeInstanceOf(Transaction::class);
    expect($this->transfer->deposit->id)->toBe($deposit->id);
});

test('it can get withdraw transaction', function () {
    $withdraw = Transaction::factory()->create();
    $this->transfer->update(['withdraw_id' => $withdraw->id]);

    expect($this->transfer->withdraw)->toBeInstanceOf(Transaction::class);
    expect($this->transfer->withdraw->id)->toBe($withdraw->id);
});
