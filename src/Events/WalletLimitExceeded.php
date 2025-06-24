<?php

namespace HWallet\LaravelMultiWallet\Events;

use HWallet\LaravelMultiWallet\Models\Wallet;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WalletLimitExceeded
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Wallet $wallet,
        public string $limitType, // 'max_balance', 'min_balance', 'daily_limit', etc.
        public float $currentValue,
        public float $limitValue,
        public ?string $operation = null
    ) {}
}
