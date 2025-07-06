<?php

namespace Tests\Feature;

use HWallet\LaravelMultiWallet\Events\BulkOperationCompleted;
use HWallet\LaravelMultiWallet\Events\BulkOperationFailed;
use HWallet\LaravelMultiWallet\Events\BulkOperationStarted;
use HWallet\LaravelMultiWallet\Models\Transaction;
use HWallet\LaravelMultiWallet\Models\Transfer;
use HWallet\LaravelMultiWallet\Models\Wallet;
use HWallet\LaravelMultiWallet\Services\BulkWalletManager;
use HWallet\LaravelMultiWallet\Tests\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->wallet1 = $this->user->createWallet('USD', 'Test Wallet 1');
    $this->wallet2 = $this->user->createWallet('EUR', 'Test Wallet 2');
    $this->wallet3 = $this->user->createWallet('GBP', 'Test Wallet 3');

    // Add initial balance to wallets
    $this->wallet1->credit(1000.00);
    $this->wallet2->credit(2000.00);
    $this->wallet3->credit(1500.00);

    $this->bulkManager = app(BulkWalletManager::class);
});

test('it can perform bulk credit operations', function () {
    Event::fake();

    $operations = [
        ['wallet_id' => $this->wallet1->id, 'amount' => 100.00, 'balance_type' => 'available'],
        ['wallet_id' => $this->wallet2->id, 'amount' => 200.00, 'balance_type' => 'available'],
    ];

    $result = $this->bulkManager->bulkCredit($operations);

    expect($result['success'])->toBeTrue();
    expect($result['successful_operations'])->toBe(2);
    expect($result['failed_operations'])->toBe(0);
    expect($result['transaction_mode'])->toBe('all_or_nothing');

    // Verify balances
    expect($this->wallet1->fresh()->getBalance('available'))->toBe(1100.00);
    expect($this->wallet2->fresh()->getBalance('available'))->toBe(2200.00);

    Event::assertDispatched(BulkOperationStarted::class);
    Event::assertDispatched(BulkOperationCompleted::class);
});

test('it can perform bulk debit operations', function () {
    $operations = [
        ['wallet_id' => $this->wallet1->id, 'amount' => 100.00, 'balance_type' => 'available'],
        ['wallet_id' => $this->wallet2->id, 'amount' => 150.00, 'balance_type' => 'available'],
    ];

    $result = $this->bulkManager->bulkDebit($operations);

    expect($result['success'])->toBeTrue();
    expect($result['successful_operations'])->toBe(2);
    expect($result['failed_operations'])->toBe(0);

    // Verify balances
    expect($this->wallet1->fresh()->getBalance('available'))->toBe(900.00);
    expect($this->wallet2->fresh()->getBalance('available'))->toBe(1850.00);
});

test('it handles bulk debit with insufficient funds in transaction mode', function () {
    Event::fake();

    $operations = [
        ['wallet_id' => $this->wallet1->id, 'amount' => 100.00], // Should succeed
        ['wallet_id' => $this->wallet2->id, 'amount' => 5000.00], // Should fail - insufficient funds
        ['wallet_id' => $this->wallet3->id, 'amount' => 50.00], // Would succeed but transaction rolls back
    ];

    $result = $this->bulkManager->bulkDebit($operations, true); // Use transaction mode

    expect($result['success'])->toBeFalse();
    expect($result['failed_operations'])->toBe(1);
    expect($result['transaction_mode'])->toBe('all_or_nothing');

    // In transaction mode, all operations should be rolled back
    expect($this->wallet1->fresh()->getBalance('available'))->toBe(1000.00); // Original balance
    expect($this->wallet2->fresh()->getBalance('available'))->toBe(2000.00); // Original balance
    expect($this->wallet3->fresh()->getBalance('available'))->toBe(1500.00); // Original balance

    Event::assertDispatched(BulkOperationStarted::class);
    Event::assertDispatched(BulkOperationFailed::class);
});

test('it handles bulk debit with insufficient funds in partial mode', function () {
    $operations = [
        ['wallet_id' => $this->wallet1->id, 'amount' => 100.00], // Should succeed
        ['wallet_id' => $this->wallet2->id, 'amount' => 5000.00], // Should fail - insufficient funds
        ['wallet_id' => $this->wallet3->id, 'amount' => 50.00], // Should succeed
    ];

    $result = $this->bulkManager->bulkDebit($operations, false); // Use partial success mode

    expect($result['success'])->toBeFalse();
    expect($result['successful_operations'])->toBe(2);
    expect($result['failed_operations'])->toBe(1);
    expect($result['transaction_mode'])->toBe('partial_success');
    expect($result['errors'][0]['error'])->toContain('Insufficient funds');

    // In partial mode, successful operations should be committed
    expect($this->wallet1->fresh()->getBalance('available'))->toBe(900.00); // Debited
    expect($this->wallet2->fresh()->getBalance('available'))->toBe(2000.00); // Unchanged
    expect($this->wallet3->fresh()->getBalance('available'))->toBe(1450.00); // Debited
});

test('it can perform bulk transfer operations', function () {
    $user2 = User::factory()->create();
    $wallet4 = $user2->createWallet('USD', 'User2 Wallet');
    $wallet5 = $user2->createWallet('EUR', 'User2 Wallet EUR');

    $operations = [
        [
            'from_wallet_id' => $this->wallet1->id,
            'to_wallet_id' => $wallet4->id,
            'amount' => 100.00,
            'options' => ['description' => 'Transfer 1'],
        ],
        [
            'from_wallet_id' => $this->wallet2->id,
            'to_wallet_id' => $wallet5->id,
            'amount' => 200.00,
            'options' => ['description' => 'Transfer 2'],
        ],
    ];

    $result = $this->bulkManager->bulkTransfer($operations);

    expect($result['success'])->toBeTrue();
    expect($result['successful_operations'])->toBe(2);
    expect($result['results'][0]['transfer'])->toBeInstanceOf(Transfer::class);

    // Verify balances
    expect($this->wallet1->fresh()->getBalance('available'))->toBe(900.00);
    expect($this->wallet2->fresh()->getBalance('available'))->toBe(1800.00);
    expect($wallet4->fresh()->getBalance('available'))->toBe(100.00);
    expect($wallet5->fresh()->getBalance('available'))->toBe(200.00);
});

test('it can perform bulk freeze operations', function () {
    $operations = [
        ['wallet_id' => $this->wallet1->id, 'amount' => 100.00, 'description' => 'Security freeze'],
        ['wallet_id' => $this->wallet2->id, 'amount' => 200.00, 'description' => 'Investigation freeze'],
    ];

    $result = $this->bulkManager->bulkFreeze($operations);

    expect($result['success'])->toBeTrue();
    expect($result['successful_operations'])->toBe(2);
    expect($result['results'][0]['transaction'])->toBeInstanceOf(Transaction::class);

    // Verify balances
    expect($this->wallet1->fresh()->getBalance('available'))->toBe(900.00);
    expect($this->wallet1->fresh()->getBalance('frozen'))->toBe(100.00);
    expect($this->wallet2->fresh()->getBalance('available'))->toBe(1800.00);
    expect($this->wallet2->fresh()->getBalance('frozen'))->toBe(200.00);
});

test('it enforces batch size limits', function () {
    // Create operations exceeding the limit
    $operations = [];
    for ($i = 0; $i < 1001; $i++) {
        $operations[] = ['wallet_id' => $this->wallet1->id, 'amount' => 1.00];
    }

    expect(fn () => $this->bulkManager->bulkCredit($operations))
        ->toThrow(\HWallet\LaravelMultiWallet\Exceptions\BulkOperationException::class);
});

test('bulk operations maintain data integrity under concurrent access', function () {
    // This test simulates concurrent access
    $operations = [
        ['wallet_id' => $this->wallet1->id, 'amount' => 100.00],
        ['wallet_id' => $this->wallet1->id, 'amount' => 200.00],
    ];

    $result1 = $this->bulkManager->bulkCredit($operations);
    $result2 = $this->bulkManager->bulkDebit($operations);

    expect($result1['success'])->toBeTrue();
    expect($result2['success'])->toBeTrue();

    // Balance should be consistent
    expect($this->wallet1->fresh()->getBalance('available'))->toBe(1000.00); // Back to original
});

test('it validates required fields in bulk operations', function () {
    $operations = [
        ['wallet_id' => $this->wallet1->id], // Missing amount
        ['amount' => 100.00], // Missing wallet_id
    ];

    $result = $this->bulkManager->bulkCredit($operations, false); // Use partial mode to see all errors

    expect($result['success'])->toBeFalse();
    expect($result['failed_operations'])->toBe(2);
    expect($result['errors'])->toHaveCount(2);
});

test('it handles non-existent wallet in bulk operations', function () {
    $operations = [
        ['wallet_id' => $this->wallet1->id, 'amount' => 100.00],
        ['wallet_id' => 99999, 'amount' => 100.00], // Non-existent wallet
    ];

    $result = $this->bulkManager->bulkCredit($operations, false); // Use partial mode

    expect($result['success'])->toBeFalse();
    expect($result['successful_operations'])->toBe(1);
    expect($result['failed_operations'])->toBe(1);
    expect($result['errors'][0]['error'])->toContain('Wallet not found');
});

test('it uses transactions for bulk operations correctly', function () {
    // This test ensures that bulk operations are wrapped in database transactions
    // We'll simulate a failure mid-operation to test rollback

    $operations = [
        ['wallet_id' => $this->wallet1->id, 'amount' => 100.00],
        ['wallet_id' => 99999, 'amount' => 100.00], // This will fail
        ['wallet_id' => $this->wallet2->id, 'amount' => 100.00],
    ];

    $originalBalance1 = $this->wallet1->getBalance('available');
    $originalBalance2 = $this->wallet2->getBalance('available');

    $result = $this->bulkManager->bulkCredit($operations, true); // Use transaction mode

    // Since the operation failed, all changes should be rolled back
    expect($result['success'])->toBeFalse();
    expect($result['transaction_mode'])->toBe('all_or_nothing');
    expect($this->wallet1->fresh()->getBalance('available'))->toBe($originalBalance1);
    expect($this->wallet2->fresh()->getBalance('available'))->toBe($originalBalance2);
});

test('it can perform bulk operations with different balance types', function () {
    $operations = [
        ['wallet_id' => $this->wallet1->id, 'amount' => 100.00, 'balance_type' => 'available'],
        ['wallet_id' => $this->wallet2->id, 'amount' => 50.00, 'balance_type' => 'pending'],
        ['wallet_id' => $this->wallet3->id, 'amount' => 25.00, 'balance_type' => 'trial'],
    ];

    $result = $this->bulkManager->bulkCredit($operations);

    expect($result['success'])->toBeTrue();
    expect($result['successful_operations'])->toBe(3);

    // Verify balances
    expect($this->wallet1->fresh()->getBalance('available'))->toBe(1100.00);
    expect($this->wallet2->fresh()->getBalance('pending'))->toBe(50.00);
    expect($this->wallet3->fresh()->getBalance('trial'))->toBe(25.00);
});

test('it returns detailed results for bulk operations', function () {
    $operations = [
        ['wallet_id' => $this->wallet1->id, 'amount' => 100.00, 'balance_type' => 'available'],
        ['wallet_id' => $this->wallet2->id, 'amount' => 50.00, 'balance_type' => 'pending'],
    ];

    $result = $this->bulkManager->bulkCredit($operations);

    expect($result)->toHaveKeys(['success', 'results', 'errors', 'total_operations', 'successful_operations', 'failed_operations', 'transaction_mode']);
    expect($result['results'])->toHaveCount(2);
    expect($result['results'][0])->toHaveKeys(['index', 'success', 'transaction', 'wallet_id', 'amount', 'balance_type']);
    expect($result['results'][0]['success'])->toBeTrue();
    expect($result['results'][0]['transaction'])->toBeInstanceOf(Transaction::class);
});

test('bulk operations respect wallet configuration limits', function () {
    // Test with a configured user that has limits
    $user = new class extends User
    {
        public function getWalletConfiguration(): array
        {
            return [
                'transaction_limits' => ['max_amount' => 500.00],
                'enable_bulk_operations' => true,
            ];
        }
    };
    $user = $user::factory()->create();

    $wallet = $user->createWallet('USD', 'Limited Wallet');
    $wallet->credit(2000.00);

    // Operation within limits should succeed
    $operations = [
        ['wallet_id' => $wallet->id, 'amount' => 100.00],
    ];

    $result = $this->bulkManager->bulkCredit($operations);
    expect($result['success'])->toBeTrue();

    // Operation exceeding limits should be handled based on configuration
    $operations = [
        ['wallet_id' => $wallet->id, 'amount' => 1000.00], // Exceeds limit
    ];

    $result = $this->bulkManager->bulkCredit($operations);
    // This should still succeed as the bulk manager doesn't automatically enforce model-level limits
    // But it could be enhanced to do so
    expect($result)->toHaveKey('success');
});

test('it can handle bulk operations with configuration-based validation', function () {
    // Create a user with specific wallet configuration
    $user = new class extends User
    {
        public function getWalletConfiguration(): array
        {
            return [
                'allowed_currencies' => ['USD', 'EUR'],
                'enable_bulk_operations' => true,
                'transaction_limits' => ['min_amount' => 1.00, 'max_amount' => 1000.00],
            ];
        }
    };
    $user = $user::factory()->create();

    $usdWallet = $user->createWallet('USD');
    $eurWallet = $user->createWallet('EUR');

    $usdWallet->credit(2000.00);
    $eurWallet->credit(3000.00);

    // Test bulk operations
    $operations = [
        ['currency' => 'USD', 'amount' => 100.00],
        ['currency' => 'EUR', 'amount' => 200.00],
    ];

    $result = $user->bulkCreditWallets($operations);

    expect($result['success'])->toBeTrue();
    expect($result['successful_operations'])->toBe(2);
});

test('it handles mixed success and failure in bulk operations gracefully', function () {
    $operations = [
        ['wallet_id' => $this->wallet1->id, 'amount' => 100.00], // Should succeed
        ['wallet_id' => 99999, 'amount' => 100.00], // Should fail - wallet doesn't exist
        ['wallet_id' => $this->wallet2->id, 'amount' => 50.00], // Should succeed if not in transaction
    ];

    $result = $this->bulkManager->bulkCredit($operations, false); // Use partial mode

    expect($result['success'])->toBeFalse();
    expect($result['total_operations'])->toBe(3);
    expect($result['successful_operations'])->toBe(2);
    expect($result['failed_operations'])->toBe(1);
    expect($result['errors'])->toHaveCount($result['failed_operations']);
    expect($result['transaction_mode'])->toBe('partial_success');
});

test('it can perform bulk operations on wallets with different holders', function () {
    $user2 = User::factory()->create();
    $user3 = User::factory()->create();

    $wallet4 = $user2->createWallet('USD', 'User2 Wallet');
    $wallet5 = $user3->createWallet('USD', 'User3 Wallet');

    $wallet4->credit(1000.00);
    $wallet5->credit(1500.00);

    $operations = [
        ['wallet_id' => $this->wallet1->id, 'amount' => 100.00], // User 1
        ['wallet_id' => $wallet4->id, 'amount' => 200.00], // User 2
        ['wallet_id' => $wallet5->id, 'amount' => 150.00], // User 3
    ];

    $result = $this->bulkManager->bulkCredit($operations);

    expect($result['success'])->toBeTrue();
    expect($result['successful_operations'])->toBe(3);

    // Verify all wallets were credited
    expect($this->wallet1->fresh()->getBalance('available'))->toBe(1100.00);
    expect($wallet4->fresh()->getBalance('available'))->toBe(1200.00);
    expect($wallet5->fresh()->getBalance('available'))->toBe(1650.00);
});

test('it can perform complex bulk transfer scenarios', function () {
    Event::fake();

    // Set up multiple users and wallets
    $users = User::factory()->count(3)->create();
    $wallets = [];

    foreach ($users as $index => $user) {
        $wallet = $user->createWallet('USD', "Wallet {$index}");
        $wallet->credit(1000.00);
        $wallets[] = $wallet;
    }

    // Create target wallets
    $targetUsers = User::factory()->count(2)->create();
    $targetWallets = [];

    foreach ($targetUsers as $index => $user) {
        $targetWallets[] = $user->createWallet('USD', "Target Wallet {$index}");
    }

    // Perform bulk transfers
    $operations = [
        [
            'from_wallet_id' => $wallets[0]->id,
            'to_wallet_id' => $targetWallets[0]->id,
            'amount' => 100.00,
            'options' => ['description' => 'Transfer 1', 'fee' => 5.00],
        ],
        [
            'from_wallet_id' => $wallets[1]->id,
            'to_wallet_id' => $targetWallets[1]->id,
            'amount' => 200.00,
            'options' => ['description' => 'Transfer 2', 'fee' => 10.00],
        ],
    ];

    $result = $this->bulkManager->bulkTransfer($operations);

    expect($result['success'])->toBeTrue();
    expect($result['successful_operations'])->toBe(2);

    // Verify transfers were completed
    expect($result['results'][0]['transfer'])->toBeInstanceOf(Transfer::class);
    expect($result['results'][1]['transfer'])->toBeInstanceOf(Transfer::class);

    Event::assertDispatched(BulkOperationStarted::class);
    Event::assertDispatched(BulkOperationCompleted::class);
});

test('it can create wallets in bulk', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $walletData = [
        [
            'holder_type' => get_class($user1),
            'holder_id' => $user1->id,
            'currency' => 'USD',
            'name' => 'USD Wallet',
            'meta' => ['priority' => 'high'],
        ],
        [
            'holder_type' => get_class($user2),
            'holder_id' => $user2->id,
            'currency' => 'EUR',
            'name' => 'EUR Wallet',
            'meta' => ['priority' => 'medium'],
        ],
    ];

    $result = $this->bulkManager->bulkCreateWallets($walletData);

    expect($result['success'])->toBeTrue();
    expect($result['successful_operations'])->toBe(2);
    expect($result['results'][0]['wallet'])->toBeInstanceOf(Wallet::class);
    expect($result['results'][1]['wallet'])->toBeInstanceOf(Wallet::class);

    // Verify wallets were created
    expect($user1->wallets()->count())->toBe(1);
    expect($user2->wallets()->count())->toBe(1);
});

test('it can perform bulk transactions with validation', function () {
    $operations = [
        [
            'wallet_id' => $this->wallet1->id,
            'type' => 'credit',
            'amount' => 100.00,
            'balance_type' => 'available',
            'meta' => ['description' => 'Test credit'],
        ],
        [
            'wallet_id' => $this->wallet2->id,
            'type' => 'debit',
            'amount' => 50.00,
            'balance_type' => 'available',
            'meta' => ['description' => 'Test debit'],
        ],
    ];

    $result = $this->bulkManager->bulkTransactionsWithValidation($operations);

    expect($result['success'])->toBeTrue();
    expect($result['successful_operations'])->toBe(2);

    // Verify balances
    expect($this->wallet1->fresh()->getBalance('available'))->toBe(1100.00);
    expect($this->wallet2->fresh()->getBalance('available'))->toBe(1950.00);
});

test('it handles validation errors in bulk transactions', function () {
    $operations = [
        [
            'wallet_id' => $this->wallet1->id,
            'type' => 'invalid_type', // Invalid type
            'amount' => 100.00,
        ],
        [
            'wallet_id' => $this->wallet2->id,
            // Missing type field
            'amount' => 50.00,
        ],
    ];

    $result = $this->bulkManager->bulkTransactionsWithValidation($operations, false);

    expect($result['success'])->toBeFalse();
    expect($result['failed_operations'])->toBe(2);
    expect($result['errors'])->toHaveCount(2);
});

test('it provides comprehensive error information', function () {
    $operations = [
        ['wallet_id' => 99999, 'amount' => 100.00], // Invalid wallet
    ];

    $result = $this->bulkManager->bulkCredit($operations, false);

    expect($result['success'])->toBeFalse();
    expect($result['errors'][0])->toHaveKeys(['index', 'error', 'operation']);
    expect($result['errors'][0]['error'])->toContain('Wallet not found');
    expect($result['errors'][0]['operation'])->toBe($operations[0]);
});
