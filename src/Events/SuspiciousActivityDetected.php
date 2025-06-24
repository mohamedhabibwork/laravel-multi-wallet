<?php

namespace HWallet\LaravelMultiWallet\Events;

use HWallet\LaravelMultiWallet\Models\Wallet;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SuspiciousActivityDetected
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Wallet $wallet,
        public string $activityType, // 'large_transaction', 'unusual_pattern', 'velocity_check', etc.
        public array $details,
        public float $riskScore,
        public ?string $detectedBy = null
    ) {}
}
