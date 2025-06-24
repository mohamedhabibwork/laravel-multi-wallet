<?php

namespace HWallet\LaravelMultiWallet\Models;

use HWallet\LaravelMultiWallet\Enums\TransferStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $from_type
 * @property int $from_id
 * @property string $to_type
 * @property int $to_id
 * @property TransferStatus $status
 * @property \Carbon\Carbon|null $status_last_changed_at
 * @property int|null $deposit_id
 * @property int|null $withdraw_id
 * @property float $discount
 * @property float $fee
 * @property string $uuid
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Model $from
 * @property-read \Illuminate\Database\Eloquent\Model $to
 * @property-read Transaction|null $deposit
 * @property-read Transaction|null $withdraw
 */
class Transfer extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \HWallet\LaravelMultiWallet\Database\Factories\TransferFactory::new();
    }

    protected $fillable = [
        'from_type',
        'from_id',
        'to_type',
        'to_id',
        'status',
        'status_last_changed_at',
        'deposit_id',
        'withdraw_id',
        'discount',
        'fee',
        'uuid',
    ];

    protected $casts = [
        'status' => TransferStatus::class,
        'status_last_changed_at' => 'datetime',
        'discount' => 'decimal:8',
        'fee' => 'decimal:8',
    ];

    protected static function boot()
    {
        parent::boot();

        static::updating(function (Transfer $transfer) {
            if ($transfer->isDirty('status')) {
                $transfer->status_last_changed_at = now();
            }
        });
    }

    /**
     * Get the from entity (polymorphic relationship)
     */
    public function from(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the to entity (polymorphic relationship)
     */
    public function to(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the deposit transaction
     */
    public function deposit(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'deposit_id');
    }

    /**
     * Get the withdraw transaction
     */
    public function withdraw(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'withdraw_id');
    }

    /**
     * Get the net amount (total amount debited from source wallet)
     */
    public function getNetAmount(): float
    {
        // Net amount = transferred amount + fee - discount
        $transferredAmount = $this->deposit ? $this->deposit->amount : 0;

        return $transferredAmount + $this->fee - $this->discount;
    }

    /**
     * Get the gross amount (original transfer amount before fees/discounts)
     */
    public function getGrossAmount(): float
    {
        // Gross amount is the base transfer amount
        return $this->deposit ? $this->deposit->amount : 0;
    }

    /**
     * Get the actual transferred amount (amount received by recipient)
     */
    public function getTransferredAmount(): float
    {
        return $this->deposit ? $this->deposit->amount : 0;
    }

    /**
     * Get the transfer amount (alias for getTransferredAmount)
     */
    public function getAmount(): float
    {
        return $this->getTransferredAmount();
    }

    /**
     * Get the fee amount
     */
    public function getFee(): float
    {
        return (float) $this->fee;
    }

    /**
     * Get the discount amount
     */
    public function getDiscount(): float
    {
        return (float) $this->discount;
    }

    /**
     * Get the status changed at timestamp
     */
    public function getStatusChangedAt()
    {
        return $this->status_last_changed_at;
    }

    /**
     * Check if transfer is pending
     */
    public function isPending(): bool
    {
        return $this->status === TransferStatus::PENDING;
    }

    /**
     * Check if transfer is paid
     */
    public function isPaid(): bool
    {
        return $this->status === TransferStatus::PAID;
    }

    /**
     * Check if transfer is confirmed
     */
    public function isConfirmed(): bool
    {
        return $this->status === TransferStatus::CONFIRMED;
    }

    /**
     * Check if transfer is rejected
     */
    public function isRejected(): bool
    {
        return $this->status === TransferStatus::REJECTED;
    }

    /**
     * Check if transfer is completed
     */
    public function isCompleted(): bool
    {
        return in_array($this->status, [TransferStatus::CONFIRMED, TransferStatus::REJECTED]);
    }

    /**
     * Mark transfer as paid
     */
    public function markAsPaid(): bool
    {
        if ($this->status !== TransferStatus::PENDING) {
            return false;
        }

        $this->status = TransferStatus::PAID;

        return $this->save();
    }

    /**
     * Mark transfer as confirmed
     */
    public function markAsConfirmed(): bool
    {
        if (! in_array($this->status, [TransferStatus::PENDING, TransferStatus::PAID])) {
            return false;
        }

        $this->status = TransferStatus::CONFIRMED;

        return $this->save();
    }

    /**
     * Mark transfer as rejected
     */
    public function markAsRejected(): bool
    {
        if ($this->isCompleted()) {
            return false;
        }

        $this->status = TransferStatus::REJECTED;

        return $this->save();
    }

    /**
     * Get the table name for this model
     */
    public function getTable(): string
    {
        return config('multi-wallet.table_names.transfers', 'transfers');
    }

    /**
     * Scope to filter by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter pending transfers
     */
    public function scopePending($query)
    {
        return $query->where('status', TransferStatus::PENDING);
    }

    /**
     * Scope to filter confirmed transfers
     */
    public function scopeConfirmed($query)
    {
        return $query->where('status', TransferStatus::CONFIRMED);
    }

    /**
     * Scope to filter rejected transfers
     */
    public function scopeRejected($query)
    {
        return $query->where('status', TransferStatus::REJECTED);
    }

    /**
     * Scope to filter completed transfers
     */
    public function scopeCompleted($query)
    {
        return $query->whereIn('status', ['confirmed', 'rejected']);
    }

    /**
     * Scope to filter transfers involving a specific entity
     */
    public function scopeInvolving($query, $entity)
    {
        return $query->where(function ($q) use ($entity) {
            $q->where('from_type', get_class($entity))
                ->where('from_id', $entity->id)
                ->orWhere('to_type', get_class($entity))
                ->where('to_id', $entity->id);
        });
    }

    /**
     * Scope to filter transfers from a specific entity
     */
    public function scopeByFrom($query, $entity)
    {
        return $query->where('from_type', get_class($entity))
            ->where('from_id', $entity->id);
    }

    /**
     * Scope to filter transfers to a specific entity
     */
    public function scopeByTo($query, $entity)
    {
        return $query->where('to_type', get_class($entity))
            ->where('to_id', $entity->id);
    }
}
