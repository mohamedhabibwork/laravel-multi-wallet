<?php

namespace HWallet\LaravelMultiWallet\Events;

use HWallet\LaravelMultiWallet\Models\Wallet;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WalletBalanceChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Wallet $wallet,
        public string $balanceType,
        public float $oldBalance,
        public float $newBalance,
        public float $change,
        public ?string $reason = null
    ) {}
}
