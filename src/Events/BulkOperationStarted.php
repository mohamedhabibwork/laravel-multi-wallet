<?php

namespace HWallet\LaravelMultiWallet\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BulkOperationStarted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $operationType;

    public int $operationCount;

    public array $metadata;

    public \DateTime $startedAt;

    public function __construct(string $operationType, int $operationCount, array $metadata = [])
    {
        $this->operationType = $operationType;
        $this->operationCount = $operationCount;
        $this->metadata = $metadata;
        $this->startedAt = new \DateTime;
    }

    /**
     * Get the event data as array
     */
    public function toArray(): array
    {
        return [
            'operation_type' => $this->operationType,
            'operation_count' => $this->operationCount,
            'metadata' => $this->metadata,
            'started_at' => $this->startedAt->format('Y-m-d H:i:s'),
        ];
    }
}
