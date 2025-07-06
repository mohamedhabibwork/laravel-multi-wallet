<?php

namespace HWallet\LaravelMultiWallet\Events;

use HWallet\LaravelMultiWallet\Models\Wallet;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WalletOperationCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Wallet $wallet;

    public string $operation;

    public array $parameters;

    public $result;

    public array $metadata;

    public \DateTime $completedAt;

    public function __construct(Wallet $wallet, string $operation, $result, array $parameters = [], array $metadata = [])
    {
        $this->wallet = $wallet;
        $this->operation = $operation;
        $this->result = $result;
        $this->parameters = $parameters;
        $this->metadata = $metadata;
        $this->completedAt = new \DateTime;
    }

    /**
     * Get the event data as array
     */
    public function toArray(): array
    {
        return [
            'wallet_id' => $this->wallet->id,
            'operation' => $this->operation,
            'result' => $this->result,
            'parameters' => $this->parameters,
            'metadata' => $this->metadata,
            'completed_at' => $this->completedAt->format('Y-m-d H:i:s'),
        ];
    }
}
