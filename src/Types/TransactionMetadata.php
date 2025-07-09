<?php

namespace HWallet\LaravelMultiWallet\Types;

use InvalidArgumentException;

/**
 * Transaction metadata value object
 */
class TransactionMetadata
{
    private array $metadata;

    public function __construct(array $metadata)
    {
        $this->metadata = $this->sanitizeMetadata($metadata);
    }

    public function getData(): array
    {
        return $this->metadata;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return isset($this->metadata[$key]);
    }

    public function set(string $key, mixed $value): void
    {
        $this->metadata[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($this->metadata[$key]);
    }

    public function merge(array $metadata): void
    {
        $this->metadata = array_merge($this->metadata, $this->sanitizeMetadata($metadata));
    }

    public function isEmpty(): bool
    {
        return empty($this->metadata);
    }

    public function count(): int
    {
        return count($this->metadata);
    }

    private function sanitizeMetadata(array $metadata): array
    {
        // Remove sensitive fields
        $sensitiveFields = ['password', 'token', 'secret', 'key', 'api_key'];

        foreach ($sensitiveFields as $field) {
            unset($metadata[$field]);
        }

        // Limit metadata size
        $maxSize = config('multi-wallet.transaction.max_metadata_size', 1000);
        if (strlen(json_encode($metadata)) > $maxSize) {
            throw new InvalidArgumentException('Transaction metadata too large');
        }

        return $metadata;
    }
}
