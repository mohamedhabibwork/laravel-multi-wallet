<?php

namespace HWallet\LaravelMultiWallet\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class BulkOperation
{
    public function __construct(
        public string $operation,
        public ?int $batchSize = null,
        public ?bool $useTransaction = null,
        public ?bool $continueOnError = null,
        public ?bool $validateBeforeExecute = null,
        public ?bool $logOperations = null,
        public ?bool $fireEvents = null,
        public ?array $metadata = null,
        public ?int $maxRetries = null,
        public ?bool $enableProgressTracking = null,
        public ?bool $enableRollback = null,
        public ?array $requiredPermissions = null
    ) {}

    /**
     * Get bulk operation configuration as array
     */
    public function toArray(): array
    {
        return array_filter([
            'operation' => $this->operation,
            'batch_size' => $this->batchSize,
            'use_transaction' => $this->useTransaction,
            'continue_on_error' => $this->continueOnError,
            'validate_before_execute' => $this->validateBeforeExecute,
            'log_operations' => $this->logOperations,
            'fire_events' => $this->fireEvents,
            'metadata' => $this->metadata,
            'max_retries' => $this->maxRetries,
            'enable_progress_tracking' => $this->enableProgressTracking,
            'enable_rollback' => $this->enableRollback,
            'required_permissions' => $this->requiredPermissions,
        ], fn ($value) => $value !== null);
    }
}
