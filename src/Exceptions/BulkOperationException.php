<?php

namespace HWallet\LaravelMultiWallet\Exceptions;

use Exception;

class BulkOperationException extends Exception
{
    protected array $operationErrors = [];

    public function __construct(string $message = '', int $code = 0, ?Exception $previous = null, array $operationErrors = [])
    {
        parent::__construct($message, $code, $previous);
        $this->operationErrors = $operationErrors;
    }

    /**
     * Get operation errors
     */
    public function getOperationErrors(): array
    {
        return $this->operationErrors;
    }

    /**
     * Set operation errors
     */
    public function setOperationErrors(array $errors): void
    {
        $this->operationErrors = $errors;
    }

    /**
     * Get error count
     */
    public function getErrorCount(): int
    {
        return count($this->operationErrors);
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'operation_errors' => $this->operationErrors,
            'error_count' => $this->getErrorCount(),
        ];
    }
}
