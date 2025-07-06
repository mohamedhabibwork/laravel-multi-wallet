<?php

namespace HWallet\LaravelMultiWallet\Events;

use HWallet\LaravelMultiWallet\Models\Wallet;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WalletOperationFailed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Wallet $wallet;

    public string $operation;

    public array $parameters;

    public string $error;

    public array $metadata;

    public \DateTime $failedAt;

    public function __construct(Wallet $wallet, string $operation, string $error, array $parameters = [], array $metadata = [])
    {
        $this->wallet = $wallet;
        $this->operation = $operation;
        $this->error = $error;
        $this->parameters = $parameters;
        $this->metadata = $metadata;
        $this->failedAt = new \DateTime;
    }

    /**
     * Get the event data as array
     */
    public function toArray(): array
    {
        return [
            'wallet_id' => $this->wallet->id,
            'operation' => $this->operation,
            'error' => $this->error,
            'parameters' => $this->parameters,
            'metadata' => $this->metadata,
            'failed_at' => $this->failedAt->format('Y-m-d H:i:s'),
        ];
    }
}
