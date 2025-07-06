<?php

namespace HWallet\LaravelMultiWallet\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BulkOperationCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $operationType;

    public int $successfulOperations;

    public array $results;

    public array $metadata;

    public \DateTime $completedAt;

    public function __construct(string $operationType, int $successfulOperations, array $results = [], array $metadata = [])
    {
        $this->operationType = $operationType;
        $this->successfulOperations = $successfulOperations;
        $this->results = $results;
        $this->metadata = $metadata;
        $this->completedAt = new \DateTime;
    }

    /**
     * Get the event data as array
     */
    public function toArray(): array
    {
        return [
            'operation_type' => $this->operationType,
            'successful_operations' => $this->successfulOperations,
            'results' => $this->results,
            'metadata' => $this->metadata,
            'completed_at' => $this->completedAt->format('Y-m-d H:i:s'),
        ];
    }
}
