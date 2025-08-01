<?php

namespace HWallet\LaravelMultiWallet\Services\Validators;

use HWallet\LaravelMultiWallet\Contracts\ValidatorInterface;
use HWallet\LaravelMultiWallet\Contracts\WalletConfigurationInterface;
use HWallet\LaravelMultiWallet\Helpers\WalletHelpers;
use HWallet\LaravelMultiWallet\Models\Transaction;
use HWallet\LaravelMultiWallet\Models\Transfer;
use HWallet\LaravelMultiWallet\Models\Wallet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

/**
 * Wallet operation validator implementation
 */
class WalletValidator implements ValidatorInterface
{
    protected WalletConfigurationInterface $config;

    public function __construct(WalletConfigurationInterface $config)
    {
        $this->config = $config;
    }

    /**
     * Validate wallet creation
     */
    public function validateWalletCreation(Model $holder, string $currency, ?string $name = null, array $attributes = []): array
    {
        $errors = [];
        $warnings = [];

        // Validate holder
        if (! WalletHelpers::hasWalletTrait($holder)) {
            $errors[] = 'Model must use HasWallets trait';
        }

        // Validate currency
        $currencyValidation = $this->validateCurrency($currency);
        if (! $this->isValid($currencyValidation)) {
            $errors = array_merge($errors, $this->getErrors($currencyValidation));
        }

        // Validate name
        if ($name !== null && (strlen($name) < 1 || strlen($name) > 255)) {
            $errors[] = 'Wallet name must be between 1 and 255 characters';
        }

        // Check uniqueness if enabled
        if ($this->config->isUniquenessEnabled()) {
            $existingWallet = $holder->wallets()
                ->where('currency', strtoupper($currency))
                ->where('name', $name)
                ->first();

            if ($existingWallet) {
                $errors[] = 'Wallet already exists for this currency and name combination';
            }
        }

        // Validate attributes
        if (isset($attributes['meta']) && ! $this->isValid($this->validateMetadata($attributes['meta'], 'wallet'))) {
            $errors = array_merge($errors, $this->getErrors($this->validateMetadata($attributes['meta'], 'wallet')));
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Validate wallet update
     */
    public function validateWalletUpdate(Wallet $wallet, array $attributes): array
    {
        $errors = [];
        $warnings = [];

        // Validate currency change
        if (isset($attributes['currency'])) {
            $currencyValidation = $this->validateCurrency($attributes['currency']);
            if (! $this->isValid($currencyValidation)) {
                $errors = array_merge($errors, $this->getErrors($currencyValidation));
            }

            // Check if currency change is allowed when wallet has balance
            if ($wallet->getTotalBalance() > 0) {
                $warnings[] = 'Changing currency on wallet with balance may cause issues';
            }
        }

        // Validate name
        if (isset($attributes['name']) && (strlen($attributes['name']) < 1 || strlen($attributes['name']) > 255)) {
            $errors[] = 'Wallet name must be between 1 and 255 characters';
        }

        // Validate metadata
        if (isset($attributes['meta']) && ! $this->isValid($this->validateMetadata($attributes['meta'], 'wallet'))) {
            $errors = array_merge($errors, $this->getErrors($this->validateMetadata($attributes['meta'], 'wallet')));
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Validate transaction creation
     */
    public function validateTransactionCreation(Wallet $wallet, float $amount, string $balanceType, array $meta = []): array
    {
        $errors = [];
        $warnings = [];

        // Validate amount
        $amountValidation = $this->validateAmount($amount);
        if (! $this->isValid($amountValidation)) {
            $errors = array_merge($errors, $this->getErrors($amountValidation));
        }

        // Validate balance type
        $balanceTypeValidation = $this->validateBalanceType($balanceType);
        if (! $this->isValid($balanceTypeValidation)) {
            $errors = array_merge($errors, $this->getErrors($balanceTypeValidation));
        }

        // Check wallet limits
        $maxBalance = $this->config->getWalletLimits()['max_balance'] ?? null;
        if ($maxBalance !== null) {
            $newBalance = $wallet->getBalance($balanceType) + $amount;
            if ($newBalance > $maxBalance) {
                $errors[] = "Transaction would exceed maximum wallet balance of {$maxBalance}";
            }
        }

        // Validate metadata
        if (! empty($meta) && ! $this->isValid($this->validateMetadata($meta, 'transaction'))) {
            $errors = array_merge($errors, $this->getErrors($this->validateMetadata($meta, 'transaction')));
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Validate transfer creation
     */
    public function validateTransferCreation(Wallet $fromWallet, Wallet $toWallet, float $amount, array $options = []): array
    {
        $errors = [];
        $warnings = [];

        // Validate amount
        $amountValidation = $this->validateAmount($amount);
        if (! $this->isValid($amountValidation)) {
            $errors = array_merge($errors, $this->getErrors($amountValidation));
        }

        // Check if wallets are different
        if ($fromWallet->id === $toWallet->id) {
            $errors[] = 'Cannot transfer to the same wallet';
        }

        // Check if currencies match
        if ($fromWallet->currency !== $toWallet->currency) {
            $errors[] = 'Transfer currencies must match';
        }

        // Check sufficient balance
        if (! $fromWallet->canDebit($amount, 'available')) {
            $errors[] = 'Insufficient available balance for transfer';
        }

        // Validate fee
        if (isset($options['fee'])) {
            $feeValidation = $this->validateAmount($options['fee']);
            if (! $this->isValid($feeValidation)) {
                $errors = array_merge($errors, $this->getErrors($feeValidation));
            }

            $maxFeePercentage = $this->config->getTransferSettings()['max_fee_percentage'] ?? 10;
            $feePercentage = ($options['fee'] / $amount) * 100;
            if ($feePercentage > $maxFeePercentage) {
                $errors[] = "Transfer fee percentage ({$feePercentage}%) exceeds maximum ({$maxFeePercentage}%)";
            }
        }

        // Validate metadata
        if (isset($options['meta']) && ! $this->isValid($this->validateMetadata($options['meta'], 'transfer'))) {
            $errors = array_merge($errors, $this->getErrors($this->validateMetadata($options['meta'], 'transfer')));
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Validate transaction (generic method)
     */
    public function validateTransaction(array $transactionData): array
    {
        $errors = [];
        $warnings = [];

        // Validate required fields
        $requiredFields = ['wallet', 'type', 'amount', 'balance_type'];
        foreach ($requiredFields as $field) {
            if (! isset($transactionData[$field])) {
                $errors[] = "Missing required field: {$field}";
            }
        }

        if (empty($errors)) {
            // Validate using existing method
            $wallet = $transactionData['wallet'];
            $amount = $transactionData['amount'];
            $balanceType = $transactionData['balance_type'];
            $metadata = $transactionData['metadata'] ?? [];

            return $this->validateTransactionCreation($wallet, $amount, $balanceType, $metadata);
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Validate search criteria
     */
    public function validateSearchCriteria(array $criteria): array
    {
        $errors = [];
        $warnings = [];

        // Validate allowed search fields
        $allowedFields = ['holder_type', 'holder_id', 'currency', 'name', 'balance_min', 'balance_max', 'created_after', 'created_before'];
        foreach ($criteria as $field => $value) {
            if (! in_array($field, $allowedFields)) {
                $errors[] = "Invalid search field: {$field}";
            }
        }

        // Validate specific field types
        if (isset($criteria['balance_min']) && ! is_numeric($criteria['balance_min'])) {
            $errors[] = 'balance_min must be numeric';
        }

        if (isset($criteria['balance_max']) && ! is_numeric($criteria['balance_max'])) {
            $errors[] = 'balance_max must be numeric';
        }

        if (isset($criteria['currency']) && ! $this->isValid($this->validateCurrency($criteria['currency']))) {
            $errors[] = 'Invalid currency in search criteria';
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Validate bulk operations
     */
    public function validateBulkOperations(array $operations, string $operationType): array
    {
        $errors = [];
        $warnings = [];

        if (empty($operations)) {
            $errors[] = 'No operations provided';

            return [
                'is_valid' => false,
                'errors' => $errors,
                'warnings' => $warnings,
            ];
        }

        $maxBatchSize = Config::get('multi-wallet.bulk_operations.max_batch_size', 1000);
        if (count($operations) > $maxBatchSize) {
            $operationCount = count($operations);
            $errors[] = "Batch size ({$operationCount}) exceeds maximum allowed ({$maxBatchSize})";
        }

        foreach ($operations as $index => $operation) {
            if (! is_array($operation)) {
                $errors[] = "Operation at index {$index} must be an array";

                continue;
            }

            // Validate required fields based on operation type
            $requiredFields = $this->getRequiredFieldsForOperationType($operationType);
            foreach ($requiredFields as $field) {
                if (! isset($operation[$field])) {
                    $errors[] = "Operation at index {$index} missing required field: {$field}";
                }
            }

            // Validate specific operation data
            if (isset($operation['amount'])) {
                $amountValidation = $this->validateAmount($operation['amount']);
                if (! $this->isValid($amountValidation)) {
                    $errors[] = "Operation at index {$index}: ".implode(', ', $this->getErrors($amountValidation));
                }
            }

            if (isset($operation['currency'])) {
                $currencyValidation = $this->validateCurrency($operation['currency']);
                if (! $this->isValid($currencyValidation)) {
                    $errors[] = "Operation at index {$index}: ".implode(', ', $this->getErrors($currencyValidation));
                }
            }
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Validate wallet configuration
     */
    public function validateWalletConfiguration(array $config): array
    {
        $errors = [];
        $warnings = [];

        // Validate currencies
        if (isset($config['allowed_currencies']) && is_array($config['allowed_currencies'])) {
            foreach ($config['allowed_currencies'] as $currency) {
                $currencyValidation = $this->validateCurrency($currency);
                if (! $this->isValid($currencyValidation)) {
                    $errors = array_merge($errors, $this->getErrors($currencyValidation));
                }
            }
        }

        // Validate limits
        if (isset($config['transaction_limits'])) {
            if (isset($config['transaction_limits']['min_amount']) && $config['transaction_limits']['min_amount'] < 0) {
                $errors[] = 'Minimum transaction amount cannot be negative';
            }

            if (isset($config['transaction_limits']['max_amount']) && $config['transaction_limits']['max_amount'] < 0) {
                $errors[] = 'Maximum transaction amount cannot be negative';
            }
        }

        if (isset($config['wallet_limits'])) {
            if (isset($config['wallet_limits']['max_balance']) && $config['wallet_limits']['max_balance'] < 0) {
                $errors[] = 'Maximum wallet balance cannot be negative';
            }
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Validate currency code
     */
    public function validateCurrency(string $currency): array
    {
        $errors = [];
        $warnings = [];

        $code = strtoupper(trim($currency));

        if (! preg_match('/^[A-Z]{3}$/', $code)) {
            $errors[] = 'Currency code must be exactly 3 uppercase letters';
        }

        if (! WalletHelpers::isCurrencySupported($code)) {
            $errors[] = "Unsupported currency: {$code}";
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Validate amount
     */
    public function validateAmount(float $amount, ?float $minAmount = null, ?float $maxAmount = null): array
    {
        $errors = [];
        $warnings = [];

        if (! is_finite($amount)) {
            $errors[] = 'Amount must be a finite number';
        }

        if ($amount < 0) {
            $errors[] = 'Amount cannot be negative';
        }

        $minAmount = $minAmount ?? $this->config->getTransactionLimits()['min_amount'] ?? 0.01;
        if ($amount < $minAmount) {
            $errors[] = "Amount must be at least {$minAmount}";
        }

        $maxAmount = $maxAmount ?? $this->config->getTransactionLimits()['max_amount'] ?? null;
        if ($maxAmount !== null && $amount > $maxAmount) {
            $errors[] = "Amount cannot exceed {$maxAmount}";
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Validate balance type
     */
    public function validateBalanceType(string $balanceType): array
    {
        $errors = [];
        $warnings = [];

        $validTypes = ['available', 'pending', 'frozen', 'trial'];
        if (! in_array($balanceType, $validTypes)) {
            $errors[] = 'Invalid balance type. Must be one of: '.implode(', ', $validTypes);
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Validate transaction type
     */
    public function validateTransactionType(string $transactionType): array
    {
        $errors = [];
        $warnings = [];

        $validTypes = ['credit', 'debit'];
        if (! in_array($transactionType, $validTypes)) {
            $errors[] = 'Invalid transaction type. Must be one of: '.implode(', ', $validTypes);
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Validate transfer status
     */
    public function validateTransferStatus(string $status): array
    {
        $errors = [];
        $warnings = [];

        $validStatuses = ['pending', 'confirmed', 'rejected', 'failed'];
        if (! in_array($status, $validStatuses)) {
            $errors[] = 'Invalid transfer status. Must be one of: '.implode(', ', $validStatuses);
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Validate metadata
     */
    public function validateMetadata(array $metadata, string $type = 'transaction'): array
    {
        $errors = [];
        $warnings = [];

        // Check for sensitive fields
        $sensitiveFields = ['password', 'token', 'secret', 'key', 'api_key'];
        foreach ($sensitiveFields as $field) {
            if (isset($metadata[$field])) {
                $warnings[] = "Metadata contains sensitive field: {$field}";
            }
        }

        // Check metadata size
        $maxSize = Config::get("multi-wallet.{$type}.max_metadata_size", 1000);
        if (strlen(json_encode($metadata)) > $maxSize) {
            $errors[] = "Metadata size exceeds maximum allowed ({$maxSize} bytes)";
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Check if validation passed
     */
    public function isValid(array $validationResult): bool
    {
        return $validationResult['is_valid'] ?? false;
    }

    /**
     * Get validation errors
     */
    public function getErrors(array $validationResult): array
    {
        return $validationResult['errors'] ?? [];
    }

    /**
     * Get validation warnings
     */
    public function getWarnings(array $validationResult): array
    {
        return $validationResult['warnings'] ?? [];
    }

    /**
     * Get required fields for operation type
     */
    private function getRequiredFieldsForOperationType(string $operationType): array
    {
        return match ($operationType) {
            'credit', 'debit' => ['wallet_id', 'amount'],
            'transfer' => ['from_wallet_id', 'to_wallet_id', 'amount'],
            'freeze', 'unfreeze' => ['wallet_id', 'amount'],
            'create_wallets' => ['holder_type', 'holder_id', 'currency'],
            'update_balances' => ['wallet_id', 'balance_type', 'amount'],
            default => [],
        };
    }
}
