<?php

namespace HWallet\LaravelMultiWallet\Types;

use HWallet\LaravelMultiWallet\Enums\BalanceType;
use HWallet\LaravelMultiWallet\Enums\TransactionType;
use HWallet\LaravelMultiWallet\Enums\TransferStatus;
use InvalidArgumentException;

/**
 * Strict type definitions for wallet operations
 */
class WalletTypes
{
    /**
     * Wallet amount value object
     */
    public static function createAmount(float $amount): Amount
    {
        return new Amount($amount);
    }

    /**
     * Currency code value object
     */
    public static function createCurrency(string $currency): Currency
    {
        return new Currency($currency);
    }

    /**
     * Wallet identifier value object
     */
    public static function createWalletId(int $id): WalletId
    {
        return new WalletId($id);
    }

    /**
     * Transaction identifier value object
     */
    public static function createTransactionId(int $id): TransactionId
    {
        return new TransactionId($id);
    }

    /**
     * Transfer identifier value object
     */
    public static function createTransferId(int $id): TransferId
    {
        return new TransferId($id);
    }

    /**
     * Wallet metadata value object
     */
    public static function createWalletMetadata(array $metadata): WalletMetadata
    {
        return new WalletMetadata($metadata);
    }

    /**
     * Transaction metadata value object
     */
    public static function createTransactionMetadata(array $metadata): TransactionMetadata
    {
        return new TransactionMetadata($metadata);
    }

    /**
     * Balance summary value object
     */
    public static function createBalanceSummary(array $balances): BalanceSummary
    {
        return new BalanceSummary($balances);
    }

    /**
     * Wallet configuration value object
     */
    public static function createWalletConfiguration(array $config): WalletConfiguration
    {
        return new WalletConfiguration($config);
    }
}

