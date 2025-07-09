<?php

namespace HWallet\LaravelMultiWallet\Types;

use InvalidArgumentException;

/**
 * Currency code value object
 */
class Currency
{
    private string $code;

    public function __construct(string $currency)
    {
        $code = strtoupper(trim($currency));

        if (! preg_match('/^[A-Z]{3}$/', $code)) {
            throw new InvalidArgumentException('Invalid currency code format');
        }

        $supportedCurrencies = config('multi-wallet.supported_currencies', [
            'USD', 'EUR', 'GBP', 'JPY', 'CAD', 'AUD', 'CHF', 'CNY',
        ]);

        if (! in_array($code, $supportedCurrencies)) {
            throw new InvalidArgumentException("Unsupported currency: {$code}");
        }

        $this->code = $code;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function equals(Currency $other): bool
    {
        return $this->code === $other->getCode();
    }

    public function __toString(): string
    {
        return $this->code;
    }
}
