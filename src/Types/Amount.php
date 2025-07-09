<?php

namespace HWallet\LaravelMultiWallet\Types;

use InvalidArgumentException;

/**
 * Amount value object with validation
 */
class Amount
{
    private float $value;

    public function __construct(float $amount)
    {
        if ($amount < 0) {
            throw new InvalidArgumentException('Amount cannot be negative');
        }

        if (!is_finite($amount)) {
            throw new InvalidArgumentException('Amount must be a finite number');
        }

        $this->value = round($amount, 8); // Support up to 8 decimal places
    }

    public function getValue(): float
    {
        return $this->value;
    }

    public function add(Amount $other): Amount
    {
        return new Amount($this->value + $other->getValue());
    }

    public function subtract(Amount $other): Amount
    {
        $result = $this->value - $other->getValue();
        if ($result < 0) {
            throw new InvalidArgumentException('Result cannot be negative');
        }
        return new Amount($result);
    }

    public function multiply(float $factor): Amount
    {
        return new Amount($this->value * $factor);
    }

    public function divide(float $divisor): Amount
    {
        if ($divisor == 0) {
            throw new InvalidArgumentException('Cannot divide by zero');
        }
        return new Amount($this->value / $divisor);
    }

    public function isZero(): bool
    {
        return $this->value == 0;
    }

    public function isPositive(): bool
    {
        return $this->value > 0;
    }

    public function equals(Amount $other): bool
    {
        return abs($this->value - $other->getValue()) < 0.000001; // Allow for floating point precision
    }

    public function greaterThan(Amount $other): bool
    {
        return $this->value > $other->getValue();
    }

    public function lessThan(Amount $other): bool
    {
        return $this->value < $other->getValue();
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
