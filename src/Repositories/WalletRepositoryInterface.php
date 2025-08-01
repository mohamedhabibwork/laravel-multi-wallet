<?php

namespace HWallet\LaravelMultiWallet\Repositories;

use HWallet\LaravelMultiWallet\Models\Wallet;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface WalletRepositoryInterface
{
    /**
     * Find wallet by ID
     */
    public function find(int $id): ?Wallet;

    /**
     * Find wallet by slug
     */
    public function findBySlug(string $slug): ?Wallet;

    /**
     * Find wallets by holder
     */
    public function findByHolder(Model $holder): Collection;

    /**
     * Find wallet by holder and currency
     */
    public function findByHolderAndCurrency(Model $holder, string $currency, ?string $name = null): ?Wallet;

    /**
     * Create a new wallet
     */
    public function create(array $attributes): Wallet;

    /**
     * Update wallet
     */
    public function update(Wallet $wallet, array $attributes): bool;

    /**
     * Delete wallet
     */
    public function delete(Wallet $wallet): bool;

    /**
     * Get wallets with balance greater than amount
     */
    public function getWalletsWithBalance(float $minBalance = 0): Collection;

    /**
     * Get wallets by currency
     */
    public function getByCurrency(string $currency): Collection;

    /**
     * Search wallets
     */
    public function search(array $criteria): Collection;

    /**
     * Get wallets with transactions for a holder
     */
    public function getWalletsWithTransactions(Model $holder): Collection;

    /**
     * Get statistics for a holder
     */
    public function getStatistics(Model $holder): array;
}
