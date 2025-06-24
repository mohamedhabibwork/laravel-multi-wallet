<?php

namespace HWallet\LaravelMultiWallet\Events;

use HWallet\LaravelMultiWallet\Models\Wallet;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WalletUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Wallet $wallet,
        public array $changes,
        public ?string $reason = null
    ) {}
}
