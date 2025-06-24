<?php

namespace HWallet\LaravelMultiWallet\Services\Strategies;

use HWallet\LaravelMultiWallet\Models\Wallet;
use Illuminate\Database\Eloquent\Model;

class DefaultUniquenessStrategy implements UniquenessStrategyInterface
{
    /**
     * Check if a wallet with the given parameters already exists
     */
    public function walletExists(Model $holder, string $currency, ?string $name = null): bool
    {
        $query = Wallet::where('holder_type', get_class($holder))
            ->where('holder_id', $holder->getKey())
            ->where('currency', $currency);

        if ($name !== null) {
            $query->where('name', $name);
        } else {
            $query->whereNull('name');
        }

        return $query->exists();
    }

    /**
     * Get the uniqueness criteria for the strategy
     */
    public function getUniquenessCriteria(): array
    {
        return [
            'holder_type',
            'holder_id',
            'currency',
            'name',
        ];
    }

    /**
     * Validate uniqueness constraints
     */
    public function validateUniqueness(Model $holder, string $currency, ?string $name = null): void
    {
        if ($this->walletExists($holder, $currency, $name)) {
            $nameText = $name ? " and name '{$name}'" : '';
            throw new \InvalidArgumentException(
                "Wallet already exists for currency '{$currency}'{$nameText}"
            );
        }
    }
}
