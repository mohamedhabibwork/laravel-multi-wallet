<?php

namespace HWallet\LaravelMultiWallet\Types;

use InvalidArgumentException;

/**
 * Transaction ID value object
 */
class TransactionId
{
    private int $id;

    public function __construct(int $id)
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Transaction ID must be positive');
        }

        $this->id = $id;
    }

    public function getValue(): int
    {
        return $this->id;
    }

    public function equals(TransactionId $other): bool
    {
        return $this->id === $other->getValue();
    }

    public function __toString(): string
    {
        return (string) $this->id;
    }
}
