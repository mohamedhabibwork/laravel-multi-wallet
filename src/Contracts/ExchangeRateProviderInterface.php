<?php

namespace HWallet\LaravelMultiWallet\Contracts;

interface ExchangeRateProviderInterface
{
    /**
     * Get the exchange rate from one currency to another
     */
    public function getRate(string $from, string $to): float;

    /**
     * Convert an amount from one currency to another
     */
    public function convert(float $amount, string $from, string $to): float;

    /**
     * Check if the provider supports a specific currency
     */
    public function supportsCurrency(string $currency): bool;

    /**
     * Get all supported currencies
     */
    public function getSupportedCurrencies(): array;
}
