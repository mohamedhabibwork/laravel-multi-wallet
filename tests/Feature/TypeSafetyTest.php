<?php

use HWallet\LaravelMultiWallet\Enums\BalanceType;
use HWallet\LaravelMultiWallet\Tests\Models\User;
use HWallet\LaravelMultiWallet\Types\Amount;
use HWallet\LaravelMultiWallet\Types\BalanceSummary;
use HWallet\LaravelMultiWallet\Types\Currency;
use HWallet\LaravelMultiWallet\Types\TransactionId;
use HWallet\LaravelMultiWallet\Types\TransactionMetadata;
use HWallet\LaravelMultiWallet\Types\TransferId;
use HWallet\LaravelMultiWallet\Types\WalletConfiguration;
use HWallet\LaravelMultiWallet\Types\WalletId;
use HWallet\LaravelMultiWallet\Types\WalletMetadata;
use HWallet\LaravelMultiWallet\Types\WalletTypes;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->wallet = $this->user->createWallet('USD', 'Test Wallet');
    $this->types = app(WalletTypes::class);
});

describe('Amount Type Safety', function () {
    it('can create valid amounts', function () {
        $amount = WalletTypes::createAmount(100.50);

        expect($amount)->toBeInstanceOf(Amount::class);
        expect($amount->getValue())->toBe(100.50);
        expect($amount->isPositive())->toBeTrue();
        expect($amount->isZero())->toBeFalse();
    });

    it('rejects negative amounts', function () {
        expect(function () {
            WalletTypes::createAmount(-50.00);
        })->toThrow(InvalidArgumentException::class);
    });

    it('rejects infinite amounts', function () {
        expect(function () {
            WalletTypes::createAmount(INF);
        })->toThrow(InvalidArgumentException::class);

        expect(function () {
            WalletTypes::createAmount(NAN);
        })->toThrow(InvalidArgumentException::class);
    });

    it('can perform amount arithmetic', function () {
        $amount1 = WalletTypes::createAmount(100.00);
        $amount2 = WalletTypes::createAmount(50.00);

        $sum = $amount1->add($amount2);
        expect($sum->getValue())->toBe(150.00);

        $difference = $amount1->subtract($amount2);
        expect($difference->getValue())->toBe(50.00);

        $product = $amount1->multiply(2);
        expect($product->getValue())->toBe(200.00);

        $quotient = $amount1->divide(2);
        expect($quotient->getValue())->toBe(50.00);
    });

    it('prevents negative results in subtraction', function () {
        $amount1 = WalletTypes::createAmount(50.00);
        $amount2 = WalletTypes::createAmount(100.00);

        expect(function () use ($amount1, $amount2) {
            $amount1->subtract($amount2);
        })->toThrow(InvalidArgumentException::class);
    });

    it('can compare amounts', function () {
        $amount1 = WalletTypes::createAmount(100.00);
        $amount2 = WalletTypes::createAmount(50.00);
        $amount3 = WalletTypes::createAmount(100.00);

        expect($amount1->greaterThan($amount2))->toBeTrue();
        expect($amount2->lessThan($amount1))->toBeTrue();
        expect($amount1->equals($amount3))->toBeTrue();
    });

    it('handles floating point precision', function () {
        $amount = WalletTypes::createAmount(123.456789);
        expect($amount->getValue())->toBe(123.45678900); // Rounded to 8 decimals
    });
});

describe('Currency Type Safety', function () {
    it('can create valid currencies', function () {
        $currency = WalletTypes::createCurrency('USD');

        expect($currency)->toBeInstanceOf(Currency::class);
        expect($currency->getCode())->toBe('USD');
        expect($currency->__toString())->toBe('USD');
    });

    it('normalizes currency codes', function () {
        $currency = WalletTypes::createCurrency('usd');
        expect($currency->getCode())->toBe('USD');

        $currency = WalletTypes::createCurrency(' eur ');
        expect($currency->getCode())->toBe('EUR');
    });

    it('rejects invalid currency formats', function () {
        expect(function () {
            WalletTypes::createCurrency('US');
        })->toThrow(InvalidArgumentException::class);

        expect(function () {
            WalletTypes::createCurrency('USDD');
        })->toThrow(InvalidArgumentException::class);

        expect(function () {
            WalletTypes::createCurrency('123');
        })->toThrow(InvalidArgumentException::class);
    });

    it('rejects unsupported currencies', function () {
        expect(function () {
            WalletTypes::createCurrency('XYZ');
        })->toThrow(InvalidArgumentException::class);
    });

    it('can compare currencies', function () {
        $usd1 = WalletTypes::createCurrency('USD');
        $usd2 = WalletTypes::createCurrency('USD');
        $eur = WalletTypes::createCurrency('EUR');

        expect($usd1->equals($usd2))->toBeTrue();
        expect($usd1->equals($eur))->toBeFalse();
    });
});

describe('ID Type Safety', function () {
    it('can create valid wallet IDs', function () {
        $walletId = WalletTypes::createWalletId(123);

        expect($walletId)->toBeInstanceOf(WalletId::class);
        expect($walletId->getValue())->toBe(123);
        expect($walletId->__toString())->toBe('123');
    });

    it('rejects invalid wallet IDs', function () {
        expect(function () {
            WalletTypes::createWalletId(0);
        })->toThrow(InvalidArgumentException::class);

        expect(function () {
            WalletTypes::createWalletId(-1);
        })->toThrow(InvalidArgumentException::class);
    });

    it('can create valid transaction IDs', function () {
        $transactionId = WalletTypes::createTransactionId(456);

        expect($transactionId)->toBeInstanceOf(TransactionId::class);
        expect($transactionId->getValue())->toBe(456);
    });

    it('can create valid transfer IDs', function () {
        $transferId = WalletTypes::createTransferId(789);

        expect($transferId)->toBeInstanceOf(TransferId::class);
        expect($transferId->getValue())->toBe(789);
    });

    it('can compare IDs', function () {
        $id1 = WalletTypes::createWalletId(123);
        $id2 = WalletTypes::createWalletId(123);
        $id3 = WalletTypes::createWalletId(456);

        expect($id1->equals($id2))->toBeTrue();
        expect($id1->equals($id3))->toBeFalse();
    });
});

describe('Metadata Type Safety', function () {
    it('can create valid wallet metadata', function () {
        $metadata = WalletTypes::createWalletMetadata([
            'purpose' => 'savings',
            'category' => 'personal',
            'priority' => 'high',
        ]);

        expect($metadata)->toBeInstanceOf(WalletMetadata::class);
        expect($metadata->get('purpose'))->toBe('savings');
        expect($metadata->has('category'))->toBeTrue();
        expect($metadata->count())->toBe(3);
    });

    it('sanitizes sensitive data', function () {
        $metadata = WalletTypes::createWalletMetadata([
            'purpose' => 'savings',
            'password' => 'secret123',
            'token' => 'abc123',
            'api_key' => 'key123',
        ]);

        expect($metadata->has('purpose'))->toBeTrue();
        expect($metadata->has('password'))->toBeFalse();
        expect($metadata->has('token'))->toBeFalse();
        expect($metadata->has('api_key'))->toBeFalse();
    });

    it('rejects oversized metadata', function () {
        $largeData = str_repeat('x', 2000);

        expect(function () use ($largeData) {
            WalletTypes::createWalletMetadata(['data' => $largeData]);
        })->toThrow(InvalidArgumentException::class);
    });

    it('can manipulate metadata', function () {
        $metadata = WalletTypes::createWalletMetadata(['initial' => 'value']);

        $metadata->set('new_key', 'new_value');
        expect($metadata->get('new_key'))->toBe('new_value');

        $metadata->remove('initial');
        expect($metadata->has('initial'))->toBeFalse();

        $metadata->merge(['merged' => 'data']);
        expect($metadata->get('merged'))->toBe('data');
    });

    it('can create transaction metadata', function () {
        $metadata = WalletTypes::createTransactionMetadata([
            'source' => 'bank_transfer',
            'reference' => 'TXN123',
            'description' => 'Payment for services',
        ]);

        expect($metadata)->toBeInstanceOf(TransactionMetadata::class);
        expect($metadata->get('source'))->toBe('bank_transfer');
        expect($metadata->isEmpty())->toBeFalse();
    });
});

describe('Balance Summary Type Safety', function () {
    it('can create valid balance summary', function () {
        $summary = WalletTypes::createBalanceSummary([
            'available' => 1000.00,
            'pending' => 50.00,
            'frozen' => 0.00,
            'trial' => 25.00,
            'total' => 1075.00,
        ]);

        expect($summary)->toBeInstanceOf(BalanceSummary::class);
        expect($summary->getAvailable())->toBe(1000.00);
        expect($summary->getPending())->toBe(50.00);
        expect($summary->getFrozen())->toBe(0.00);
        expect($summary->getTrial())->toBe(25.00);
        expect($summary->getTotal())->toBe(1075.00);
    });

    it('validates required balance fields', function () {
        expect(function () {
            WalletTypes::createBalanceSummary([
                'available' => 1000.00,
                'pending' => 50.00,
                // Missing frozen, trial, total
            ]);
        })->toThrow(InvalidArgumentException::class);
    });

    it('validates numeric balance values', function () {
        expect(function () {
            WalletTypes::createBalanceSummary([
                'available' => 'invalid',
                'pending' => 50.00,
                'frozen' => 0.00,
                'trial' => 25.00,
                'total' => 1075.00,
            ]);
        })->toThrow(InvalidArgumentException::class);
    });

    it('can get balance by type', function () {
        $summary = WalletTypes::createBalanceSummary([
            'available' => 1000.00,
            'pending' => 50.00,
            'frozen' => 0.00,
            'trial' => 25.00,
            'total' => 1075.00,
        ]);

        expect($summary->getBalance(BalanceType::AVAILABLE))->toBe(1000.00);
        expect($summary->getBalance(BalanceType::PENDING))->toBe(50.00);
        expect($summary->getBalance(BalanceType::FROZEN))->toBe(0.00);
        expect($summary->getBalance(BalanceType::TRIAL))->toBe(25.00);
    });

    it('can check balance status', function () {
        $summary = WalletTypes::createBalanceSummary([
            'available' => 1000.00,
            'pending' => 50.00,
            'frozen' => 0.00,
            'trial' => 25.00,
            'total' => 1075.00,
        ]);

        expect($summary->hasBalance(BalanceType::AVAILABLE))->toBeTrue();
        expect($summary->hasBalance(BalanceType::FROZEN))->toBeFalse();
        expect($summary->isZero())->toBeFalse();

        $zeroSummary = WalletTypes::createBalanceSummary([
            'available' => 0.00,
            'pending' => 0.00,
            'frozen' => 0.00,
            'trial' => 0.00,
            'total' => 0.00,
        ]);

        expect($zeroSummary->isZero())->toBeTrue();
    });
});

describe('Configuration Type Safety', function () {
    it('can create valid wallet configuration', function () {
        $config = WalletTypes::createWalletConfiguration([
            'default_currency' => 'USD',
            'allowed_currencies' => ['USD', 'EUR', 'GBP'],
            'transaction_limits' => [
                'min_amount' => 0.01,
                'max_amount' => 10000.00,
            ],
            'wallet_limits' => [
                'max_balance' => 100000.00,
            ],
            'enable_events' => true,
            'enable_audit_log' => true,
        ]);

        expect($config)->toBeInstanceOf(WalletConfiguration::class);
        expect($config->getDefaultCurrency())->toBe('USD');
        expect($config->getAllowedCurrencies())->toContain('USD');
        expect($config->getMinTransactionAmount())->toBe(0.01);
        expect($config->getMaxTransactionAmount())->toBe(10000.00);
        expect($config->areEventsEnabled())->toBeTrue();
    });

    it('validates currency codes in configuration', function () {
        expect(function () {
            WalletTypes::createWalletConfiguration([
                'allowed_currencies' => ['USD', 'INVALID', 'EUR'],
            ]);
        })->toThrow(InvalidArgumentException::class);
    });

    it('validates negative amounts in configuration', function () {
        expect(function () {
            WalletTypes::createWalletConfiguration([
                'transaction_limits' => ['min_amount' => -1.00],
            ]);
        })->toThrow(InvalidArgumentException::class);

        expect(function () {
            WalletTypes::createWalletConfiguration([
                'wallet_limits' => ['max_balance' => -100.00],
            ]);
        })->toThrow(InvalidArgumentException::class);
    });

    it('can check configuration settings', function () {
        $config = WalletTypes::createWalletConfiguration([
            'allowed_currencies' => ['USD', 'EUR'],
            'uniqueness_enabled' => true,
            'enable_events' => false,
        ]);

        expect($config->isCurrencyAllowed('USD'))->toBeTrue();
        expect($config->isCurrencyAllowed('GBP'))->toBeFalse();
        expect($config->isUniquenessEnabled())->toBeTrue();
        expect($config->areEventsEnabled())->toBeFalse();
    });
});

describe('Type Integration', function () {
    it('can use types together in operations', function () {
        $amount = WalletTypes::createAmount(100.00);
        $currency = WalletTypes::createCurrency('USD');
        $metadata = WalletTypes::createWalletMetadata(['purpose' => 'test']);

        // Simulate a transaction operation
        $transaction = [
            'amount' => $amount->getValue(),
            'currency' => $currency->getCode(),
            'metadata' => $metadata->getData(),
        ];

        expect($transaction['amount'])->toBe(100.00);
        expect($transaction['currency'])->toBe('USD');
        expect($transaction['metadata']['purpose'])->toBe('test');
    });

    it('maintains type safety across operations', function () {
        $amount1 = WalletTypes::createAmount(100.00);
        $amount2 = WalletTypes::createAmount(50.00);

        // Operations should return new instances
        $sum = $amount1->add($amount2);
        expect($sum)->not->toBe($amount1);
        expect($sum)->not->toBe($amount2);
        expect($sum->getValue())->toBe(150.00);

        // Original amounts should be unchanged
        expect($amount1->getValue())->toBe(100.00);
        expect($amount2->getValue())->toBe(50.00);
    });
});

describe('Factory Methods', function () {
    it('provides factory methods for all types', function () {
        expect(WalletTypes::createAmount(100.00))->toBeInstanceOf(Amount::class);
        expect(WalletTypes::createCurrency('USD'))->toBeInstanceOf(Currency::class);
        expect(WalletTypes::createWalletId(1))->toBeInstanceOf(WalletId::class);
        expect(WalletTypes::createTransactionId(1))->toBeInstanceOf(TransactionId::class);
        expect(WalletTypes::createTransferId(1))->toBeInstanceOf(TransferId::class);
        expect(WalletTypes::createWalletMetadata([]))->toBeInstanceOf(WalletMetadata::class);
        expect(WalletTypes::createTransactionMetadata([]))->toBeInstanceOf(TransactionMetadata::class);
        expect(WalletTypes::createBalanceSummary([
            'available' => 0, 'pending' => 0, 'frozen' => 0, 'trial' => 0, 'total' => 0,
        ]))->toBeInstanceOf(BalanceSummary::class);
        expect(WalletTypes::createWalletConfiguration([]))->toBeInstanceOf(WalletConfiguration::class);
    });
});

describe('Error Handling', function () {
    it('provides meaningful error messages', function () {
        try {
            WalletTypes::createAmount(-100.00);
            expect(false)->toBeTrue(); // Should not reach here
        } catch (InvalidArgumentException $e) {
            expect($e->getMessage())->toContain('Amount cannot be negative');
        }

        try {
            WalletTypes::createCurrency('XYZ');
            expect(false)->toBeTrue(); // Should not reach here
        } catch (InvalidArgumentException $e) {
            expect($e->getMessage())->toContain('Unsupported currency');
        }
    });

    it('handles edge cases gracefully', function () {
        // Very small amounts
        $smallAmount = WalletTypes::createAmount(0.00000001);
        expect($smallAmount->getValue())->toBe(0.00000001);

        // Very large amounts
        $largeAmount = WalletTypes::createAmount(999999999.99999999);
        expect($largeAmount->getValue())->toBe(999999999.99999999);
    });
});
