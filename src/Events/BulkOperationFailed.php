<?php

namespace HWallet\LaravelMultiWallet\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BulkOperationFailed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $operationType;

    public array $errors;

    public int $failedOperations;

    public array $metadata;

    public \DateTime $failedAt;

    public function __construct(string $operationType, array $errors, array $metadata = [])
    {
        $this->operationType = $operationType;
        $this->errors = $errors;
        $this->failedOperations = count($errors);
        $this->metadata = $metadata;
        $this->failedAt = new \DateTime;
    }

    /**
     * Get the event data as array
     */
    public function toArray(): array
    {
        return [
            'operation_type' => $this->operationType,
            'errors' => $this->errors,
            'failed_operations' => $this->failedOperations,
            'metadata' => $this->metadata,
            'failed_at' => $this->failedAt->format('Y-m-d H:i:s'),
        ];
    }
}
