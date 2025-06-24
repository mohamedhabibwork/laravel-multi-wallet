<?php

namespace HWallet\LaravelMultiWallet\Services;

use HWallet\LaravelMultiWallet\Contracts\ExchangeRateProviderInterface;

class DefaultExchangeRateProvider implements ExchangeRateProviderInterface
{
    protected array $supportedCurrencies;

    protected array $exchangeRates;

    public function __construct(array $supportedCurrencies = [], array $exchangeRates = [])
    {
        $this->supportedCurrencies = $supportedCurrencies ?: ['USD', 'EUR', 'GBP', 'JPY', 'CAD', 'AUD'];
        $this->exchangeRates = $exchangeRates;
    }

    /**
     * Get the exchange rate from one currency to another
     */
    public function getRate(string $from, string $to): float
    {
        if ($from === $to) {
            return 1.0;
        }

        // Check if we have a direct rate
        $directKey = "{$from}_{$to}";
        if (isset($this->exchangeRates[$directKey])) {
            return $this->exchangeRates[$directKey];
        }

        // Check if we have a reverse rate
        $reverseKey = "{$to}_{$from}";
        if (isset($this->exchangeRates[$reverseKey])) {
            return 1 / $this->exchangeRates[$reverseKey];
        }

        // Default 1:1 rate for all supported currencies
        if ($this->supportsCurrency($from) && $this->supportsCurrency($to)) {
            return 1.0;
        }

        throw new \InvalidArgumentException("Exchange rate not available for {$from} to {$to}");
    }

    /**
     * Convert an amount from one currency to another
     */
    public function convert(float $amount, string $from, string $to): float
    {
        $rate = $this->getRate($from, $to);

        return $amount * $rate;
    }

    /**
     * Check if the provider supports a specific currency
     */
    public function supportsCurrency(string $currency): bool
    {
        return in_array(strtoupper($currency), array_map('strtoupper', $this->supportedCurrencies));
    }

    /**
     * Get all supported currencies
     */
    public function getSupportedCurrencies(): array
    {
        return $this->supportedCurrencies;
    }

    /**
     * Add a custom exchange rate
     */
    public function setRate(string $from, string $to, float $rate): void
    {
        $this->exchangeRates["{$from}_{$to}"] = $rate;
    }

    /**
     * Add multiple exchange rates
     */
    public function setRates(array $rates): void
    {
        foreach ($rates as $key => $rate) {
            if (is_string($key) && strpos($key, '_') !== false) {
                $this->exchangeRates[$key] = $rate;
            }
        }
    }

    /**
     * Add a supported currency
     */
    public function addSupportedCurrency(string $currency): void
    {
        $currency = strtoupper($currency);
        if (! in_array($currency, $this->supportedCurrencies)) {
            $this->supportedCurrencies[] = $currency;
        }
    }
}
