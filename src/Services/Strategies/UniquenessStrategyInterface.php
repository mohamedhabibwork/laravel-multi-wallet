<?php

namespace HWallet\LaravelMultiWallet\Services\Strategies;

use Illuminate\Database\Eloquent\Model;

interface UniquenessStrategyInterface
{
    /**
     * Check if a wallet with the given parameters already exists
     */
    public function walletExists(Model $holder, string $currency, ?string $name = null): bool;

    /**
     * Get the uniqueness criteria for the strategy
     */
    public function getUniquenessCriteria(): array;

    /**
     * Validate uniqueness constraints
     */
    public function validateUniqueness(Model $holder, string $currency, ?string $name = null): void;
}
