<?php

namespace HWallet\LaravelMultiWallet\Enums;

enum TransactionType: string
{
    case CREDIT = 'credit';
    case DEBIT = 'debit';

    public function label(): string
    {
        return match ($this) {
            self::CREDIT => 'Credit',
            self::DEBIT => 'Debit',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::CREDIT => 'Adding funds to the wallet',
            self::DEBIT => 'Removing funds from the wallet',
        };
    }

    public function isCredit(): bool
    {
        return $this === self::CREDIT;
    }

    public function isDebit(): bool
    {
        return $this === self::DEBIT;
    }

    public static function toArray(): array
    {
        return array_map(fn ($case) => $case->value, self::cases());
    }
}
