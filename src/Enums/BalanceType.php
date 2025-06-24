<?php

namespace HWallet\LaravelMultiWallet\Enums;

enum BalanceType: string
{
    case PENDING = 'pending';
    case AVAILABLE = 'available';
    case FROZEN = 'frozen';
    case TRIAL = 'trial';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::AVAILABLE => 'Available',
            self::FROZEN => 'Frozen',
            self::TRIAL => 'Trial',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::PENDING => 'Transactions in progress, not yet confirmed',
            self::AVAILABLE => 'Balance ready for use (excludes pending and frozen)',
            self::FROZEN => 'Restricted balance, cannot be used for transactions',
            self::TRIAL => 'Reserved for trial transactions and testing',
        };
    }

    public static function toArray(): array
    {
        return array_map(fn ($case) => $case->value, self::cases());
    }
}
