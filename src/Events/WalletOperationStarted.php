<?php

namespace HWallet\LaravelMultiWallet\Events;

use HWallet\LaravelMultiWallet\Models\Wallet;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WalletOperationStarted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Wallet $wallet;

    public string $operation;

    public array $parameters;

    public array $metadata;

    public \DateTime $startedAt;

    public function __construct(Wallet $wallet, string $operation, array $parameters = [], array $metadata = [])
    {
        $this->wallet = $wallet;
        $this->operation = $operation;
        $this->parameters = $parameters;
        $this->metadata = $metadata;
        $this->startedAt = new \DateTime;
    }

    /**
     * Get the event data as array
     */
    public function toArray(): array
    {
        return [
            'wallet_id' => $this->wallet->id,
            'operation' => $this->operation,
            'parameters' => $this->parameters,
            'metadata' => $this->metadata,
            'started_at' => $this->startedAt->format('Y-m-d H:i:s'),
        ];
    }
}
