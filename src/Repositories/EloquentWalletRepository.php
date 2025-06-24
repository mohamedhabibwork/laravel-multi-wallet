<?php

namespace HWallet\LaravelMultiWallet\Repositories;

use HWallet\LaravelMultiWallet\Models\Wallet;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class EloquentWalletRepository implements WalletRepositoryInterface
{
    /**
     * Find wallet by ID
     */
    public function find(int $id): ?Wallet
    {
        return Wallet::find($id);
    }

    /**
     * Find wallet by slug
     */
    public function findBySlug(string $slug): ?Wallet
    {
        return Wallet::where('slug', $slug)->first();
    }

    /**
     * Find wallets by holder
     */
    public function findByHolder(Model $holder): Collection
    {
        return Wallet::where('holder_type', get_class($holder))
            ->where('holder_id', $holder->getKey())
            ->get();
    }

    /**
     * Find wallet by holder and currency
     */
    public function findByHolderAndCurrency(Model $holder, string $currency, ?string $name = null): ?Wallet
    {
        $query = Wallet::where('holder_type', get_class($holder))
            ->where('holder_id', $holder->getKey())
            ->where('currency', $currency);

        if ($name !== null) {
            $query->where('name', $name);
        } else {
            $query->whereNull('name');
        }

        return $query->first();
    }

    /**
     * Create a new wallet
     */
    public function create(array $attributes): Wallet
    {
        return Wallet::create($attributes);
    }

    /**
     * Update wallet
     */
    public function update(Wallet $wallet, array $attributes): bool
    {
        return $wallet->update($attributes);
    }

    /**
     * Delete wallet
     */
    public function delete(Wallet $wallet): bool
    {
        return $wallet->delete();
    }

    /**
     * Get wallets with balance greater than amount
     */
    public function getWalletsWithBalance(float $minBalance = 0): Collection
    {
        return Wallet::whereRaw('(balance_pending + balance_available + balance_frozen + balance_trial) >= ?', [$minBalance])
            ->get();
    }

    /**
     * Get wallets by currency
     */
    public function getByCurrency(string $currency): Collection
    {
        return Wallet::where('currency', $currency)->get();
    }

    /**
     * Search wallets
     */
    public function search(array $criteria): Collection
    {
        $query = Wallet::query();

        if (isset($criteria['holder_type'])) {
            $query->where('holder_type', $criteria['holder_type']);
        }

        if (isset($criteria['holder_id'])) {
            $query->where('holder_id', $criteria['holder_id']);
        }

        if (isset($criteria['currency'])) {
            $query->where('currency', $criteria['currency']);
        }

        if (isset($criteria['name'])) {
            $query->where('name', $criteria['name']);
        }

        if (isset($criteria['min_balance'])) {
            $query->whereRaw('(balance_pending + balance_available + balance_frozen + balance_trial) >= ?', [$criteria['min_balance']]);
        }

        if (isset($criteria['max_balance'])) {
            $query->whereRaw('(balance_pending + balance_available + balance_frozen + balance_trial) <= ?', [$criteria['max_balance']]);
        }

        if (isset($criteria['created_from'])) {
            $query->where('created_at', '>=', $criteria['created_from']);
        }

        if (isset($criteria['created_to'])) {
            $query->where('created_at', '<=', $criteria['created_to']);
        }

        if (isset($criteria['has_meta'])) {
            foreach ($criteria['has_meta'] as $key => $value) {
                $query->whereJsonContains('meta->'.$key, $value);
            }
        }

        return $query->get();
    }
}
