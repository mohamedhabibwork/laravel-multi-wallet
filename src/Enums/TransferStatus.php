<?php

namespace HWallet\LaravelMultiWallet\Enums;

enum TransferStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case CONFIRMED = 'confirmed';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::PAID => 'Paid',
            self::CONFIRMED => 'Confirmed',
            self::REJECTED => 'Rejected',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::PENDING => 'Transfer initiated but not processed',
            self::PAID => 'Transfer processed and payment completed',
            self::CONFIRMED => 'Transfer confirmed and finalized',
            self::REJECTED => 'Transfer rejected and cancelled',
        };
    }

    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    public function isPaid(): bool
    {
        return $this === self::PAID;
    }

    public function isConfirmed(): bool
    {
        return $this === self::CONFIRMED;
    }

    public function isRejected(): bool
    {
        return $this === self::REJECTED;
    }

    public function isCompleted(): bool
    {
        return in_array($this, [self::CONFIRMED, self::REJECTED]);
    }

    public static function toArray(): array
    {
        return array_map(fn ($case) => $case->value, self::cases());
    }
}
