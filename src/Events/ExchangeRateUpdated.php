<?php

namespace HWallet\LaravelMultiWallet\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExchangeRateUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $fromCurrency,
        public string $toCurrency,
        public float $oldRate,
        public float $newRate,
        public ?string $source = null
    ) {}
}
