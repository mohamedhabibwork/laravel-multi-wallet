<?php

namespace HWallet\LaravelMultiWallet\Repositories;

use HWallet\LaravelMultiWallet\Models\Wallet;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class EloquentWalletRepository implements WalletRepositoryInterface
{
    /**
     * Find wallet by ID with caching
     */
    public function find(int $id): ?Wallet
    {
        return Cache::remember("wallet.{$id}", 300, function () use ($id) {
            return Wallet::find($id);
        });
    }

    /**
     * Find wallet by slug with caching
     */
    public function findBySlug(string $slug): ?Wallet
    {
        return Cache::remember("wallet.slug.{$slug}", 300, function () use ($slug) {
            return Wallet::where('slug', $slug)->first();
        });
    }

    /**
     * Find wallets by holder using optimized scope
     */
    public function findByHolder(Model $holder): Collection
    {
        return Wallet::byHolder($holder)->active()->get();
    }

    /**
     * Find wallet by holder and currency using optimized query
     */
    public function findByHolderAndCurrency(Model $holder, string $currency, ?string $name = null): ?Wallet
    {
        $cacheKey = "wallet.holder.{$holder->getKey()}.{$currency}.".($name ?? 'default');

        return Cache::remember($cacheKey, 300, function () use ($holder, $currency, $name) {
            $query = Wallet::byHolder($holder)->byCurrency($currency);

            if ($name !== null) {
                $query->where('name', $name);
            } else {
                $query->whereNull('name');
            }

            return $query->first();
        });
    }

    /**
     * Create a new wallet
     */
    public function create(array $attributes): Wallet
    {
        $wallet = Wallet::create($attributes);

        // Clear relevant caches
        $this->clearHolderCache($wallet);

        return $wallet;
    }

    /**
     * Update wallet
     */
    public function update(Wallet $wallet, array $attributes): bool
    {
        $result = $wallet->update($attributes);

        // Clear caches
        Cache::forget("wallet.{$wallet->id}");
        Cache::forget("wallet.slug.{$wallet->slug}");
        $this->clearHolderCache($wallet);

        return $result;
    }

    /**
     * Delete wallet
     */
    public function delete(Wallet $wallet): bool
    {
        $result = $wallet->delete();

        // Clear caches
        Cache::forget("wallet.{$wallet->id}");
        Cache::forget("wallet.slug.{$wallet->slug}");
        $this->clearHolderCache($wallet);

        return $result;
    }

    /**
     * Get wallets with balance greater than amount using optimized query
     */
    public function getWalletsWithBalance(float $minBalance = 0): Collection
    {
        return Wallet::withBalance($minBalance)->active()->get();
    }

    /**
     * Get wallets by currency using scope
     */
    public function getByCurrency(string $currency): Collection
    {
        return Wallet::byCurrency($currency)->active()->get();
    }

    /**
     * Search wallets with optimized queries
     */
    public function search(array $criteria): Collection
    {
        $query = Wallet::query()->active();

        // Use scopes where available
        if (isset($criteria['currency'])) {
            $query->byCurrency($criteria['currency']);
        }

        if (isset($criteria['min_balance'])) {
            $query->withBalance($criteria['min_balance']);
        }

        if (isset($criteria['holder_type'])) {
            $query->where('holder_type', $criteria['holder_type']);
        }

        if (isset($criteria['holder_id'])) {
            $query->where('holder_id', $criteria['holder_id']);
        }

        if (isset($criteria['name'])) {
            $query->where('name', $criteria['name']);
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

        if (isset($criteria['with_recent_activity'])) {
            $query->withRecentActivity($criteria['with_recent_activity']);
        }

        return $query->get();
    }

    /**
     * Get wallets with transactions for dashboard
     */
    public function getWalletsWithTransactions(Model $holder): Collection
    {
        return Wallet::byHolder($holder)
            ->withTransactions()
            ->active()
            ->get();
    }

    /**
     * Get wallet statistics
     */
    public function getStatistics(Model $holder): array
    {
        $cacheKey = "wallet.stats.{$holder->getKey()}";

        return Cache::remember($cacheKey, 600, function () use ($holder) {
            return DB::table('wallets')
                ->where('holder_type', get_class($holder))
                ->where('holder_id', $holder->getKey())
                ->whereNull('deleted_at')
                ->selectRaw('
                    COUNT(*) as total_wallets,
                    COUNT(DISTINCT currency) as currencies_count,
                    SUM(balance_available) as total_available,
                    SUM(balance_pending) as total_pending,
                    SUM(balance_frozen) as total_frozen,
                    SUM(balance_trial) as total_trial,
                    SUM(balance_pending + balance_available + balance_frozen + balance_trial) as grand_total
                ')
                ->first();
        });
    }

    /**
     * Bulk operations for performance
     */
    public function bulkUpdate(array $walletIds, array $attributes): int
    {
        $result = Wallet::whereIn('id', $walletIds)->update($attributes);

        // Clear caches for updated wallets
        foreach ($walletIds as $id) {
            Cache::forget("wallet.{$id}");
        }

        return $result;
    }

    /**
     * Clear holder-related caches
     */
    private function clearHolderCache(Wallet $wallet): void
    {
        $pattern = "wallet.holder.{$wallet->holder_id}.*";
        Cache::forget($pattern);
        Cache::forget("wallet.stats.{$wallet->holder_id}");
    }
}
