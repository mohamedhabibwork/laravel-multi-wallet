<?php

namespace HWallet\LaravelMultiWallet\Models;

use HWallet\LaravelMultiWallet\Contracts\WalletInterface;
use HWallet\LaravelMultiWallet\Enums\BalanceType;
use HWallet\LaravelMultiWallet\Enums\TransactionType;
use HWallet\LaravelMultiWallet\Events\WalletBalanceChanged;
use HWallet\LaravelMultiWallet\Events\WalletFrozen;
use HWallet\LaravelMultiWallet\Events\WalletUnfrozen;
use HWallet\LaravelMultiWallet\Exceptions\InsufficientFundsException;
use HWallet\LaravelMultiWallet\Exceptions\InvalidBalanceTypeException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $holder_type
 * @property int $holder_id
 * @property string $currency
 * @property string|null $name
 * @property string $slug
 * @property string|null $description
 * @property array|null $meta
 * @property float $balance_pending
 * @property float $balance_available
 * @property float $balance_frozen
 * @property float $balance_trial
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Model $holder
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Transaction> $transactions
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Transfer> $incomingTransfers
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Transfer> $outgoingTransfers
 */
class Wallet extends Model implements WalletInterface
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \HWallet\LaravelMultiWallet\Database\Factories\WalletFactory::new();
    }

    protected $fillable = [
        'holder_type',
        'holder_id',
        'currency',
        'name',
        'slug',
        'description',
        'meta',
        'balance_pending',
        'balance_available',
        'balance_frozen',
        'balance_trial',
    ];

    protected $casts = [
        'meta' => 'array',
        'balance_pending' => 'decimal:8',
        'balance_available' => 'decimal:8',
        'balance_frozen' => 'decimal:8',
        'balance_trial' => 'decimal:8',
    ];

    protected $with = ['holder']; // Eager load holder by default

    /**
     * Query scopes for optimization
     */
    public function scopeByHolder($query, Model $holder)
    {
        return $query->where('holder_type', get_class($holder))
            ->where('holder_id', $holder->getKey());
    }

    public function scopeByCurrency($query, string $currency)
    {
        return $query->where('currency', $currency);
    }

    public function scopeWithBalance($query, float $minBalance = 0)
    {
        return $query->whereRaw('(balance_pending + balance_available + balance_frozen + balance_trial) >= ?', [$minBalance]);
    }

    public function scopeWithAvailableBalance($query, float $minBalance = 0)
    {
        return $query->where('balance_available', '>=', $minBalance);
    }

    public function scopeActive($query)
    {
        return $query->whereNull('deleted_at');
    }

    public function scopeWithTransactions($query)
    {
        return $query->with(['transactions' => function ($q) {
            $q->orderBy('created_at', 'desc')->limit(10);
        }]);
    }

    public function scopeWithRecentActivity($query, int $days = 30)
    {
        return $query->whereHas('transactions', function ($q) use ($days) {
            $q->where('created_at', '>=', now()->subDays($days));
        });
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function (Wallet $wallet) {
            if (empty($wallet->slug)) {
                $wallet->slug = static::generateUniqueSlug($wallet);
            }
        });

        static::saving(function (Wallet $wallet) {
            if (empty($wallet->slug)) {
                $wallet->slug = static::generateUniqueSlug($wallet);
            }
        });
    }

    /**
     * Get the holder (polymorphic relationship)
     */
    public function holder(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get all transactions for this wallet
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get incoming transfers (deposits)
     */
    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(Transfer::class, 'deposit_id');
    }

    /**
     * Get outgoing transfers (withdrawals)
     */
    public function outgoingTransfers(): HasMany
    {
        return $this->hasMany(Transfer::class, 'withdraw_id');
    }

    /**
     * Get the wallet balance for a specific balance type
     */
    public function getBalance(BalanceType|string $balanceType = 'available'): float
    {
        $balanceType = $this->normalizeBalanceType($balanceType);

        return match ($balanceType) {
            BalanceType::PENDING => (float) $this->balance_pending,
            BalanceType::AVAILABLE => (float) $this->balance_available,
            BalanceType::FROZEN => (float) $this->balance_frozen,
            BalanceType::TRIAL => (float) $this->balance_trial,
        };
    }

    /**
     * Get the total balance across all balance types
     */
    public function getTotalBalance(): float
    {
        return $this->balance_pending + $this->balance_available + $this->balance_frozen + $this->balance_trial;
    }

    /**
     * Credit the wallet with the specified amount
     */
    public function credit(float $amount, BalanceType|string $balanceType = 'available', array $meta = []): Transaction
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Credit amount must be positive');
        }

        $balanceType = $this->normalizeBalanceType($balanceType);
        $reason = $meta['description'] ?? null;

        return DB::transaction(function () use ($amount, $balanceType, $meta, $reason) {
            $this->updateBalance($balanceType, $amount, true, $reason);

            return $this->createTransaction(
                TransactionType::CREDIT,
                $amount,
                $balanceType,
                $meta
            );
        });
    }

    /**
     * Debit the wallet with the specified amount
     */
    public function debit(float $amount, BalanceType|string $balanceType = 'available', array $meta = []): Transaction
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Debit amount must be positive');
        }

        $balanceType = $this->normalizeBalanceType($balanceType);
        $reason = $meta['description'] ?? null;

        if (! $this->canDebit($amount, $balanceType)) {
            throw new InsufficientFundsException("Insufficient {$balanceType->value} balance");
        }

        return DB::transaction(function () use ($amount, $balanceType, $meta, $reason) {
            $this->updateBalance($balanceType, $amount, false, $reason);

            return $this->createTransaction(
                TransactionType::DEBIT,
                $amount,
                $balanceType,
                $meta
            );
        });
    }

    /**
     * Check if the wallet can be debited with the specified amount
     */
    public function canDebit(float $amount, BalanceType|string $balanceType = 'available'): bool
    {
        $balanceType = $this->normalizeBalanceType($balanceType);

        return $this->getBalance($balanceType) >= $amount;
    }

    /**
     * Move amount to pending balance
     */
    public function moveToPending(float $amount, string $description = ''): Transaction
    {
        if (! $this->canDebit($amount, BalanceType::AVAILABLE)) {
            throw new InsufficientFundsException('Insufficient available balance');
        }

        return DB::transaction(function () use ($amount, $description) {
            $this->updateBalance(BalanceType::AVAILABLE, $amount, false, $description);
            $this->updateBalance(BalanceType::PENDING, $amount, true, $description);

            return $this->createTransaction(
                TransactionType::DEBIT,
                $amount,
                BalanceType::AVAILABLE,
                ['description' => $description, 'moved_to_pending' => true]
            );
        });
    }

    /**
     * Confirm pending amount and move to available
     */
    public function confirmPending(float $amount, string $description = ''): bool
    {
        if (! $this->canDebit($amount, BalanceType::PENDING)) {
            return false;
        }

        return DB::transaction(function () use ($amount, $description) {
            $this->updateBalance(BalanceType::PENDING, $amount, false, $description);
            $this->updateBalance(BalanceType::AVAILABLE, $amount, true, $description);

            $this->createTransaction(
                TransactionType::CREDIT,
                $amount,
                BalanceType::AVAILABLE,
                ['description' => $description, 'confirmed_from_pending' => true]
            );

            return true;
        });
    }

    /**
     * Cancel pending amount and return to available
     */
    public function cancelPending(float $amount, string $description = ''): bool
    {
        if (! $this->canDebit($amount, BalanceType::PENDING)) {
            return false;
        }

        return DB::transaction(function () use ($amount, $description) {
            $this->updateBalance(BalanceType::PENDING, $amount, false, $description);
            $this->updateBalance(BalanceType::AVAILABLE, $amount, true, $description);

            $this->createTransaction(
                TransactionType::CREDIT,
                $amount,
                BalanceType::AVAILABLE,
                ['description' => $description, 'cancelled_from_pending' => true]
            );

            return true;
        });
    }

    /**
     * Freeze amount from available balance
     */
    public function freeze(float $amount, string $description = ''): Transaction
    {
        if (! $this->canDebit($amount, BalanceType::AVAILABLE)) {
            throw new InsufficientFundsException('Insufficient available balance');
        }

        return DB::transaction(function () use ($amount, $description) {
            $this->updateBalance(BalanceType::AVAILABLE, $amount, false, $description);
            $this->updateBalance(BalanceType::FROZEN, $amount, true, $description);

            $transaction = $this->createTransaction(
                TransactionType::DEBIT,
                $amount,
                BalanceType::AVAILABLE,
                ['description' => $description, 'frozen' => true]
            );

            event(new WalletFrozen($this, $amount, $description));

            return $transaction;
        });
    }

    /**
     * Unfreeze amount and return to available balance
     */
    public function unfreeze(float $amount, string $description = ''): Transaction
    {
        if (! $this->canDebit($amount, BalanceType::FROZEN)) {
            throw new InsufficientFundsException('Insufficient frozen balance');
        }

        return DB::transaction(function () use ($amount, $description) {
            $this->updateBalance(BalanceType::FROZEN, $amount, false, $description);
            $this->updateBalance(BalanceType::AVAILABLE, $amount, true, $description);

            $transaction = $this->createTransaction(
                TransactionType::CREDIT,
                $amount,
                BalanceType::AVAILABLE,
                ['description' => $description, 'unfrozen' => true]
            );

            event(new WalletUnfrozen($this, $amount, $description));

            return $transaction;
        });
    }

    /**
     * Add trial balance
     */
    public function addTrialBalance(float $amount, string $description = ''): Transaction
    {
        return $this->credit($amount, BalanceType::TRIAL, ['description' => $description, 'trial_balance' => true]);
    }

    /**
     * Convert trial balance to available
     */
    public function convertTrialToAvailable(float $amount, string $description = ''): bool
    {
        if (! $this->canDebit($amount, BalanceType::TRIAL)) {
            return false;
        }

        return DB::transaction(function () use ($amount, $description) {
            $this->updateBalance(BalanceType::TRIAL, $amount, false, $description);
            $this->updateBalance(BalanceType::AVAILABLE, $amount, true, $description);

            $this->createTransaction(
                TransactionType::CREDIT,
                $amount,
                BalanceType::AVAILABLE,
                ['description' => $description, 'converted_from_trial' => true]
            );

            return true;
        });
    }

    /**
     * Create a new transaction record
     */
    protected function createTransaction(
        TransactionType $type,
        float $amount,
        BalanceType $balanceType,
        array $meta = []
    ): Transaction {
        /** @var Transaction $transaction */
        $transaction = $this->transactions()->create([
            'payable_type' => $this->holder_type,
            'payable_id' => $this->holder_id,
            'type' => $type->value,
            'amount' => $amount,
            'balance_type' => $balanceType->value,
            'confirmed' => true,
            'meta' => $meta,
            'uuid' => Str::uuid()->toString(),
        ]);

        return $transaction;
    }

    /**
     * Update the wallet balance
     */
    protected function updateBalance(BalanceType $balanceType, float $amount, bool $isCredit, ?string $reason = null): void
    {
        $column = match ($balanceType) {
            BalanceType::PENDING => 'balance_pending',
            BalanceType::AVAILABLE => 'balance_available',
            BalanceType::FROZEN => 'balance_frozen',
            BalanceType::TRIAL => 'balance_trial',
        };

        $oldBalance = $this->$column;
        $newBalance = $isCredit ? $oldBalance + $amount : $oldBalance - $amount;
        $change = $isCredit ? $amount : -$amount;

        $this->update([$column => $newBalance]);

        event(new WalletBalanceChanged(
            $this,
            $balanceType->value,
            $oldBalance,
            $newBalance,
            $change,
            $reason
        ));
    }

    /**
     * Normalize balance type to enum
     */
    protected function normalizeBalanceType(BalanceType|string $balanceType): BalanceType
    {
        if ($balanceType instanceof BalanceType) {
            return $balanceType;
        }

        return match (strtolower($balanceType)) {
            'pending' => BalanceType::PENDING,
            'available' => BalanceType::AVAILABLE,
            'frozen' => BalanceType::FROZEN,
            'trial' => BalanceType::TRIAL,
            default => throw new InvalidBalanceTypeException("Invalid balance type: {$balanceType}"),
        };
    }

    /**
     * Generate a unique slug for the wallet
     */
    public static function generateUniqueSlug(Wallet $wallet): string
    {
        $baseSlug = Str::slug($wallet->name ?: $wallet->currency ?: 'wallet');
        $slug = $baseSlug;
        $counter = 1;

        while (static::where('slug', $slug)->whereNull('deleted_at')->exists()) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Get the table name for the model
     */
    public function getTable(): string
    {
        return config('multi-wallet.table_names.wallets', 'wallets');
    }
}
