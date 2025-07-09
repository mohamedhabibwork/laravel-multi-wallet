<?php

namespace HWallet\LaravelMultiWallet\Types;

use HWallet\LaravelMultiWallet\Enums\BalanceType;
use InvalidArgumentException;

/**
 * Balance summary value object
 */
class BalanceSummary
{
    private array $balances;

    public function __construct(array $balances)
    {
        $this->validateBalances($balances);
        $this->balances = $balances;
    }

    public function getAvailable(): float
    {
        return $this->balances['available'] ?? 0.0;
    }

    public function getPending(): float
    {
        return $this->balances['pending'] ?? 0.0;
    }

    public function getFrozen(): float
    {
        return $this->balances['frozen'] ?? 0.0;
    }

    public function getTrial(): float
    {
        return $this->balances['trial'] ?? 0.0;
    }

    public function getTotal(): float
    {
        return $this->balances['total'] ?? 0.0;
    }

    public function getBalance(BalanceType $balanceType): float
    {
        return match ($balanceType) {
            BalanceType::AVAILABLE => $this->getAvailable(),
            BalanceType::PENDING => $this->getPending(),
            BalanceType::FROZEN => $this->getFrozen(),
            BalanceType::TRIAL => $this->getTrial(),
        };
    }

    public function getAllBalances(): array
    {
        return $this->balances;
    }

    public function hasBalance(BalanceType $balanceType): bool
    {
        return $this->getBalance($balanceType) > 0;
    }

    public function isZero(): bool
    {
        return $this->getTotal() == 0;
    }

    private function validateBalances(array $balances): void
    {
        $requiredFields = ['available', 'pending', 'frozen', 'trial', 'total'];

        foreach ($requiredFields as $field) {
            if (! isset($balances[$field])) {
                throw new InvalidArgumentException("Missing required balance field: {$field}");
            }

            if (! is_numeric($balances[$field])) {
                throw new InvalidArgumentException("Balance field {$field} must be numeric");
            }
        }
    }
}
