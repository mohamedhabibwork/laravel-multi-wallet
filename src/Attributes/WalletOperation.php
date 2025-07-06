<?php

namespace HWallet\LaravelMultiWallet\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class WalletOperation
{
    public function __construct(
        public string $operation,
        public ?string $description = null,
        public ?bool $requiresValidation = null,
        public ?bool $logTransaction = null,
        public ?bool $fireEvents = null,
        public ?array $requiredPermissions = null,
        public ?bool $auditLog = null,
        public ?array $metadata = null
    ) {}

    /**
     * Get operation configuration as array
     */
    public function toArray(): array
    {
        return array_filter([
            'operation' => $this->operation,
            'description' => $this->description,
            'requires_validation' => $this->requiresValidation,
            'log_transaction' => $this->logTransaction,
            'fire_events' => $this->fireEvents,
            'required_permissions' => $this->requiredPermissions,
            'audit_log' => $this->auditLog,
            'metadata' => $this->metadata,
        ], fn ($value) => $value !== null);
    }
}
