<?php

namespace HWallet\LaravelMultiWallet\Events;

use HWallet\LaravelMultiWallet\Models\Wallet;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WalletReconciled
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Wallet $wallet,
        public array $discrepancies,
        public array $corrections,
        public ?string $reconciledBy = null
    ) {}
}
