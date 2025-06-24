<?php

namespace HWallet\LaravelMultiWallet\Events;

use HWallet\LaravelMultiWallet\Models\Transfer;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransferRejected
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Transfer $transfer,
        public string $reason,
        public ?string $rejectedBy = null
    ) {}
}
