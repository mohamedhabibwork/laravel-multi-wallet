<?php

namespace HWallet\LaravelMultiWallet\Contracts;

interface WalletConfigurationInterface
{
    /**
     * Get the default currency for wallets
     */
    public function getDefaultCurrency(): string;

    /**
     * Get the exchange rate provider instance
     */
    public function getExchangeRateProvider(): ExchangeRateProviderInterface;

    /**
     * Get wallet limits configuration
     */
    public function getWalletLimits(): array;

    /**
     * Get transaction limits configuration
     */
    public function getTransactionLimits(): array;

    /**
     * Get enabled balance types
     */
    public function getBalanceTypes(): array;

    /**
     * Check if wallet uniqueness is enabled
     */
    public function isUniquenessEnabled(): bool;

    /**
     * Get the uniqueness strategy
     */
    public function getUniquenessStrategy(): string;

    /**
     * Get fee calculation settings
     */
    public function getFeeCalculationSettings(): array;

    /**
     * Get metadata schema validation rules
     */
    public function getMetadataSchema(): array;

    /**
     * Check if audit logging is enabled
     */
    public function isAuditLoggingEnabled(): bool;

    /**
     * Get transfer settings
     */
    public function getTransferSettings(): array;
}
