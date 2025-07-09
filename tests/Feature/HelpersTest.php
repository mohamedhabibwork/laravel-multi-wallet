<?php

use HWallet\LaravelMultiWallet\Helpers\WalletHelpers;
use HWallet\LaravelMultiWallet\Tests\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->wallet = $this->user->createWallet('USD', 'Test Wallet');
    $this->wallet->credit(1000);
});

describe('Helper Functions', function () {
    it('can format amount with currency', function () {
        $formatted = wallet_format_amount(1234.56, 'USD');
        expect($formatted)->toBe('$1,234.56');

        $formatted = wallet_format_amount(1234.56, 'EUR');
        expect($formatted)->toBe('€1,234.56');
    });

    it('can check if currency is supported', function () {
        expect(wallet_is_currency_supported('USD'))->toBeTrue();
        expect(wallet_is_currency_supported('EUR'))->toBeTrue();
        expect(wallet_is_currency_supported('XYZ'))->toBeFalse();
    });

    it('can validate amount within limits', function () {
        expect(wallet_validate_amount(100, 50, 200))->toBeTrue();
        expect(wallet_validate_amount(25, 50, 200))->toBeFalse();
        expect(wallet_validate_amount(250, 50, 200))->toBeFalse();
    });

    it('can calculate transfer fee', function () {
        $fee = wallet_calculate_fee(1000, 2.5); // 2.5% fee
        expect($fee)->toBe(25.0);

        $fee = wallet_calculate_fee(1000, 0, 5.0); // Fixed fee
        expect($fee)->toBe(5.0);

        $fee = wallet_calculate_fee(1000, 1.5, 2.0); // Both percentage and fixed
        expect($fee)->toBe(17.0);
    });

    it('can round amount to specified decimals', function () {
        expect(wallet_round_amount(123.456789, 2))->toBe(123.46);
        expect(wallet_round_amount(123.456789, 4))->toBe(123.4568);
        expect(wallet_round_amount(123.456789, 0))->toBe(123.0);
    });

    it('can calculate percentage', function () {
        $result = wallet_calculate_percentage(1000, 15);
        expect($result)->toBe(150.0);

        $result = wallet_calculate_percentage(500, 2.5);
        expect($result)->toBe(12.5);
    });

    it('can format balance summary', function () {
        $summary = wallet_format_balance_summary($this->wallet);

        expect($summary)->toHaveKeys(['available', 'pending', 'frozen', 'trial', 'total']);
        expect($summary['available'])->toBe('$1,000.00');
        expect($summary['total'])->toBe('$1,000.00');
    });

    it('can get user wallet summary', function () {
        $this->user->createWallet('EUR', 'Euro Wallet');
        $this->user->creditWallet('EUR', 500);

        $summary = wallet_get_user_summary($this->user);

        expect($summary)->toHaveKeys(['wallets', 'total_balance', 'currencies']);
        expect($summary['currencies'])->toHaveCount(2);
        expect($summary['wallets'])->toHaveCount(2);
    });
});

describe('Helper Class Methods', function () {
    it('can validate currency codes', function () {
        $helpers = app(WalletHelpers::class);

        expect($helpers->isValidCurrency('USD'))->toBeTrue();
        expect($helpers->isValidCurrency('usd'))->toBeTrue();
        expect($helpers->isValidCurrency('US'))->toBeFalse();
        expect($helpers->isValidCurrency('USDD'))->toBeFalse();
    });

    it('can calculate transaction fees with different strategies', function () {
        $helpers = app(WalletHelpers::class);

        $fee = $helpers->calculateTransactionFee(1000, 'percentage', 2.5);
        expect($fee)->toBe(25.0);

        $fee = $helpers->calculateTransactionFee(1000, 'fixed', 10.0);
        expect($fee)->toBe(10.0);

        $fee = $helpers->calculateTransactionFee(1000, 'tiered', [
            'tiers' => [
                ['min' => 0, 'max' => 500, 'rate' => 1.0],
                ['min' => 500, 'max' => 1000, 'rate' => 2.0],
                ['min' => 1000, 'max' => null, 'rate' => 3.0],
            ],
        ]);
        expect($fee)->toBe(30.0);
    });

    it('can validate metadata', function () {
        $helpers = app(WalletHelpers::class);

        $validMetadata = ['purpose' => 'savings', 'category' => 'personal'];
        expect($helpers->validateMetadata($validMetadata))->toBeTrue();

        $invalidMetadata = ['password' => 'secret123'];
        expect($helpers->validateMetadata($invalidMetadata))->toBeFalse();
    });

    it('can get currency symbol', function () {
        $helpers = app(WalletHelpers::class);

        expect($helpers->getCurrencySymbol('USD'))->toBe('$');
        expect($helpers->getCurrencySymbol('EUR'))->toBe('€');
        expect($helpers->getCurrencySymbol('GBP'))->toBe('£');
        expect($helpers->getCurrencySymbol('JPY'))->toBe('¥');
    });

    it('can calculate balance statistics', function () {
        $helpers = app(WalletHelpers::class);

        // Create multiple wallets with different balances
        $wallet1 = $this->user->createWallet('USD', 'Wallet 1');
        $wallet1->credit(1000);

        $wallet2 = $this->user->createWallet('USD', 'Wallet 2');
        $wallet2->credit(500);

        $wallets = [$wallet1, $wallet2];
        $stats = $helpers->calculateBalanceStatistics($wallets);

        expect($stats)->toHaveKeys(['total', 'average', 'min', 'max', 'count']);
        expect($stats['total'])->toBe(1500.0);
        expect($stats['average'])->toBe(750.0);
        expect($stats['min'])->toBe(500.0);
        expect($stats['max'])->toBe(1000.0);
        expect($stats['count'])->toBe(2);
    });
});

describe('Performance and Optimization', function () {
    it('can handle large amounts efficiently', function () {
        $helpers = app(WalletHelpers::class);

        $largeAmount = 999999999.99;
        $formatted = $helpers->formatAmount($largeAmount, 'USD');

        expect($formatted)->toBe('$999,999,999.99');
        expect($helpers->roundAmount($largeAmount, 2))->toBe(999999999.99);
    });

    it('can handle precision calculations', function () {
        $helpers = app(WalletHelpers::class);

        // Test floating point precision
        $amount1 = 0.1;
        $amount2 = 0.2;
        $sum = $helpers->addAmounts($amount1, $amount2);

        expect($sum)->toBe(0.3);

        // Test with high precision
        $preciseAmount = 123.123456789;
        $rounded = $helpers->roundAmount($preciseAmount, 8);

        expect($rounded)->toBe(123.12345679);
    });

    it('can handle multiple currency operations', function () {
        $helpers = app(WalletHelpers::class);

        $amounts = [
            ['amount' => 100, 'currency' => 'USD'],
            ['amount' => 200, 'currency' => 'EUR'],
            ['amount' => 300, 'currency' => 'GBP'],
        ];

        $formatted = $helpers->formatMultipleCurrencies($amounts);

        expect($formatted)->toHaveCount(3);
        expect($formatted[0])->toBe('$100.00');
        expect($formatted[1])->toBe('€200.00');
        expect($formatted[2])->toBe('£300.00');
    });
});

describe('Error Handling', function () {
    it('handles invalid currency gracefully', function () {
        $helpers = app(WalletHelpers::class);

        // Test that the method throws an exception for invalid currency
        $exceptionThrown = false;
        try {
            $helpers->formatAmount(100, 'INVALID');
        } catch (Exception $e) {
            $exceptionThrown = true;
            // In test environment, the exception type might vary, but functionality works
            expect($e->getMessage())->toContain('Invalid currency');
        }

        expect($exceptionThrown)->toBeTrue();
    });

    it('handles negative amounts appropriately', function () {
        $helpers = app(WalletHelpers::class);

        expect($helpers->validateTransferAmount(-100))->toBeFalse();
        expect($helpers->validateTransferAmount(0))->toBeFalse();
        expect($helpers->validateTransferAmount(100))->toBeTrue();
    });

    it('handles invalid metadata gracefully', function () {
        $helpers = app(WalletHelpers::class);

        $largeMetadata = ['data' => str_repeat('x', 2000)];
        expect($helpers->validateMetadata($largeMetadata))->toBeFalse();
    });
});
