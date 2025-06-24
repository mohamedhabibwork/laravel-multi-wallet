<?php

namespace HWallet\LaravelMultiWallet\Models;

use HWallet\LaravelMultiWallet\Enums\BalanceType;
use HWallet\LaravelMultiWallet\Enums\TransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $payable_type
 * @property int $payable_id
 * @property int $wallet_id
 * @property TransactionType $type
 * @property float $amount
 * @property BalanceType $balance_type
 * @property bool $confirmed
 * @property array|null $meta
 * @property string $uuid
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Model $payable
 * @property-read Wallet $wallet
 */
class Transaction extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \HWallet\LaravelMultiWallet\Database\Factories\TransactionFactory::new();
    }

    protected $fillable = [
        'payable_type',
        'payable_id',
        'wallet_id',
        'type',
        'amount',
        'balance_type',
        'confirmed',
        'meta',
        'uuid',
    ];

    protected $casts = [
        'type' => TransactionType::class,
        'balance_type' => BalanceType::class,
        'amount' => 'decimal:8',
        'confirmed' => 'boolean',
        'meta' => 'array',
    ];

    /**
     * Get the payable model (polymorphic relationship)
     */
    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the wallet that this transaction belongs to
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * Check if this is a credit transaction
     */
    public function isCredit(): bool
    {
        return $this->type === TransactionType::CREDIT;
    }

    /**
     * Check if this is a debit transaction
     */
    public function isDebit(): bool
    {
        return $this->type === TransactionType::DEBIT;
    }

    /**
     * Check if this transaction is confirmed
     */
    public function isConfirmed(): bool
    {
        return $this->confirmed;
    }

    /**
     * Confirm the transaction
     */
    public function confirm(): bool
    {
        if ($this->confirmed) {
            return true;
        }

        $this->confirmed = true;

        return $this->save();
    }

    /**
     * Get the signed amount (positive for credit, negative for debit)
     */
    public function getSignedAmount(): float
    {
        return $this->isCredit() ? $this->amount : -$this->amount;
    }

    /**
     * Get a description from metadata
     */
    public function getDescription(): ?string
    {
        return $this->meta['description'] ?? null;
    }

    /**
     * Get the table name for this model
     */
    public function getTable(): string
    {
        return config('multi-wallet.table_names.transactions', 'transactions');
    }

    /**
     * Scope to filter by wallet
     */
    public function scopeByWallet($query, Wallet $wallet)
    {
        return $query->where('wallet_id', $wallet->id);
    }

    /**
     * Scope to filter by transaction type
     */
    public function scopeByType($query, TransactionType|string $type)
    {
        if (is_string($type)) {
            $type = TransactionType::tryFrom($type);
        }

        return $query->where('type', $type);
    }

    /**
     * Scope to filter by balance type
     */
    public function scopeByBalanceType($query, BalanceType|string $balanceType)
    {
        if (is_string($balanceType)) {
            $balanceType = BalanceType::tryFrom($balanceType);
        }

        return $query->where('balance_type', $balanceType);
    }

    /**
     * Scope to filter confirmed transactions
     */
    public function scopeConfirmed($query)
    {
        return $query->where('confirmed', true);
    }

    /**
     * Scope to filter pending transactions
     */
    public function scopePending($query)
    {
        return $query->where('confirmed', false);
    }
}
