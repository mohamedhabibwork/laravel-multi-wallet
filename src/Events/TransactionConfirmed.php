<?php

namespace HWallet\LaravelMultiWallet\Events;

use HWallet\LaravelMultiWallet\Models\Transaction;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransactionConfirmed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Transaction $transaction,
        public ?string $reason = null
    ) {}
}
