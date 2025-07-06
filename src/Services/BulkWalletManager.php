<?php

namespace HWallet\LaravelMultiWallet\Services;

use HWallet\LaravelMultiWallet\Attributes\BulkOperation;
use HWallet\LaravelMultiWallet\Contracts\BulkWalletOperationInterface;
use HWallet\LaravelMultiWallet\Contracts\WalletConfigurationInterface;
use HWallet\LaravelMultiWallet\Enums\BalanceType;
use HWallet\LaravelMultiWallet\Events\BulkOperationCompleted;
use HWallet\LaravelMultiWallet\Events\BulkOperationFailed;
use HWallet\LaravelMultiWallet\Events\BulkOperationStarted;
use HWallet\LaravelMultiWallet\Exceptions\BulkOperationException;
use HWallet\LaravelMultiWallet\Models\Transaction;
use HWallet\LaravelMultiWallet\Models\Transfer;
use HWallet\LaravelMultiWallet\Models\Wallet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

class BulkWalletManager implements BulkWalletOperationInterface
{
    protected WalletConfigurationInterface $config;

    protected WalletManager $walletManager;

    protected int $defaultBatchSize = 100;

    public function __construct(WalletConfigurationInterface $config, WalletManager $walletManager)
    {
        $this->config = $config;
        $this->walletManager = $walletManager;
    }

    #[BulkOperation('bulk_credit', batchSize: 100, useTransaction: true, validateBeforeExecute: true)]
    public function bulkCredit(array $operations, bool $useTransaction = true): array
    {
        return $this->executeBulkOperation('credit', $operations, $useTransaction);
    }

    #[BulkOperation('bulk_debit', batchSize: 100, useTransaction: true, validateBeforeExecute: true)]
    public function bulkDebit(array $operations, bool $useTransaction = true): array
    {
        return $this->executeBulkOperation('debit', $operations, $useTransaction);
    }

    #[BulkOperation('bulk_transfer', batchSize: 50, useTransaction: true, validateBeforeExecute: true)]
    public function bulkTransfer(array $operations, bool $useTransaction = true): array
    {
        return $this->executeBulkOperation('transfer', $operations, $useTransaction);
    }

    #[BulkOperation('bulk_freeze', batchSize: 100, useTransaction: true, validateBeforeExecute: true)]
    public function bulkFreeze(array $operations, bool $useTransaction = true): array
    {
        return $this->executeBulkOperation('freeze', $operations, $useTransaction);
    }

    #[BulkOperation('bulk_unfreeze', batchSize: 100, useTransaction: true, validateBeforeExecute: true)]
    public function bulkUnfreeze(array $operations, bool $useTransaction = true): array
    {
        return $this->executeBulkOperation('unfreeze', $operations, $useTransaction);
    }

    #[BulkOperation('bulk_create_wallets', batchSize: 50, useTransaction: true, validateBeforeExecute: true)]
    public function bulkCreateWallets(array $walletData, bool $useTransaction = true): array
    {
        return $this->executeBulkOperation('create_wallets', $walletData, $useTransaction);
    }

    #[BulkOperation('bulk_update_balances', batchSize: 100, useTransaction: true, validateBeforeExecute: true)]
    public function bulkUpdateBalances(array $operations, bool $useTransaction = true): array
    {
        return $this->executeBulkOperation('update_balances', $operations, $useTransaction);
    }

    #[BulkOperation('bulk_transactions_with_validation', batchSize: 50, useTransaction: true, validateBeforeExecute: true)]
    public function bulkTransactionsWithValidation(array $operations, bool $useTransaction = true): array
    {
        return $this->executeBulkOperation('transactions_with_validation', $operations, $useTransaction);
    }

    /**
     * Execute bulk operation with proper transaction handling
     */
    protected function executeBulkOperation(string $operationType, array $operations, bool $useTransaction = true): array
    {
        $this->validateBulkOperations($operations, $operationType);

        Event::dispatch(new BulkOperationStarted("bulk_{$operationType}", count($operations)));

        $results = [];
        $errors = [];

        if ($useTransaction) {
            // All-or-nothing mode: use transaction with rollback on any failure
            try {
                DB::transaction(function () use ($operations, $operationType, &$results, &$errors) {
                    foreach ($operations as $index => $operation) {
                        $result = $this->processSingleOperation($operation, $operationType, $index);

                        if ($result['success']) {
                            $results[] = $result;
                        } else {
                            $errors[] = $result;
                            // Throw exception to trigger rollback
                            throw new BulkOperationException("Operation {$index} failed: {$result['error']}");
                        }
                    }
                });
            } catch (BulkOperationException $e) {
                // Transaction rolled back, errors are already populated
            }
        } else {
            // Partial success mode: continue processing even if some operations fail
            foreach ($operations as $index => $operation) {
                $result = $this->processSingleOperation($operation, $operationType, $index);

                if ($result['success']) {
                    $results[] = $result;
                } else {
                    $errors[] = $result;
                }
            }
        }

        $success = empty($errors);

        if ($success) {
            Event::dispatch(new BulkOperationCompleted("bulk_{$operationType}", count($results)));
        } else {
            Event::dispatch(new BulkOperationFailed("bulk_{$operationType}", $errors));
        }

        return [
            'success' => $success,
            'results' => $results,
            'errors' => $errors,
            'total_operations' => count($operations),
            'successful_operations' => count($results),
            'failed_operations' => count($errors),
            'transaction_mode' => $useTransaction ? 'all_or_nothing' : 'partial_success',
        ];
    }

    /**
     * Process a single operation
     */
    protected function processSingleOperation(array $operation, string $operationType, int $index): array
    {
        try {
            switch ($operationType) {
                case 'credit':
                    return $this->processCreditOperation($operation, $index);
                case 'debit':
                    return $this->processDebitOperation($operation, $index);
                case 'transfer':
                    return $this->processTransferOperation($operation, $index);
                case 'freeze':
                    return $this->processFreezeOperation($operation, $index);
                case 'unfreeze':
                    return $this->processUnfreezeOperation($operation, $index);
                case 'create_wallets':
                    return $this->processCreateWalletOperation($operation, $index);
                case 'update_balances':
                    return $this->processUpdateBalanceOperation($operation, $index);
                case 'transactions_with_validation':
                    return $this->processTransactionWithValidation($operation, $index);
                default:
                    throw new \InvalidArgumentException("Unknown operation type: {$operationType}");
            }
        } catch (\Exception $e) {
            return [
                'index' => $index,
                'success' => false,
                'error' => $e->getMessage(),
                'operation' => $operation,
            ];
        }
    }

    /**
     * Process credit operation
     */
    protected function processCreditOperation(array $operation, int $index): array
    {
        $this->validateSingleOperation($operation, ['wallet_id', 'amount']);

        $wallet = $this->getWalletById($operation['wallet_id']);
        $amount = $operation['amount'];
        $balanceType = BalanceType::tryFrom($operation['balance_type'] ?? 'available') ?? BalanceType::AVAILABLE;
        $meta = $operation['meta'] ?? [];

        $transaction = $wallet->credit($amount, $balanceType, $meta);

        return [
            'index' => $index,
            'success' => true,
            'transaction' => $transaction,
            'wallet_id' => $wallet->id,
            'amount' => $amount,
            'balance_type' => $balanceType->value,
        ];
    }

    /**
     * Process debit operation
     */
    protected function processDebitOperation(array $operation, int $index): array
    {
        $this->validateSingleOperation($operation, ['wallet_id', 'amount']);

        $wallet = $this->getWalletById($operation['wallet_id']);
        $amount = $operation['amount'];
        $balanceType = BalanceType::tryFrom($operation['balance_type'] ?? 'available') ?? BalanceType::AVAILABLE;
        $meta = $operation['meta'] ?? [];

        // Check if wallet can be debited
        if (! $wallet->canDebit($amount, $balanceType)) {
            throw new \Exception("Insufficient funds for wallet {$wallet->id}");
        }

        $transaction = $wallet->debit($amount, $balanceType, $meta);

        return [
            'index' => $index,
            'success' => true,
            'transaction' => $transaction,
            'wallet_id' => $wallet->id,
            'amount' => $amount,
            'balance_type' => $balanceType->value,
        ];
    }

    /**
     * Process transfer operation
     */
    protected function processTransferOperation(array $operation, int $index): array
    {
        $this->validateSingleOperation($operation, ['from_wallet_id', 'to_wallet_id', 'amount']);

        $fromWallet = $this->getWalletById($operation['from_wallet_id']);
        $toWallet = $this->getWalletById($operation['to_wallet_id']);
        $amount = $operation['amount'];
        $options = $operation['options'] ?? [];

        $transfer = $this->walletManager->transfer($fromWallet, $toWallet, $amount, $options);

        return [
            'index' => $index,
            'success' => true,
            'transfer' => $transfer,
            'from_wallet_id' => $fromWallet->id,
            'to_wallet_id' => $toWallet->id,
            'amount' => $amount,
        ];
    }

    /**
     * Process freeze operation
     */
    protected function processFreezeOperation(array $operation, int $index): array
    {
        $this->validateSingleOperation($operation, ['wallet_id', 'amount']);

        $wallet = $this->getWalletById($operation['wallet_id']);
        $amount = $operation['amount'];
        $description = $operation['description'] ?? '';

        $transaction = $wallet->freeze($amount, $description);

        return [
            'index' => $index,
            'success' => true,
            'transaction' => $transaction,
            'wallet_id' => $wallet->id,
            'amount' => $amount,
        ];
    }

    /**
     * Process unfreeze operation
     */
    protected function processUnfreezeOperation(array $operation, int $index): array
    {
        $this->validateSingleOperation($operation, ['wallet_id', 'amount']);

        $wallet = $this->getWalletById($operation['wallet_id']);
        $amount = $operation['amount'];
        $description = $operation['description'] ?? '';

        $transaction = $wallet->unfreeze($amount, $description);

        return [
            'index' => $index,
            'success' => true,
            'transaction' => $transaction,
            'wallet_id' => $wallet->id,
            'amount' => $amount,
        ];
    }

    /**
     * Process create wallet operation
     */
    protected function processCreateWalletOperation(array $operation, int $index): array
    {
        $this->validateSingleOperation($operation, ['holder_type', 'holder_id', 'currency']);

        $holderType = $operation['holder_type'];
        $holderId = $operation['holder_id'];
        $currency = $operation['currency'];
        $name = $operation['name'] ?? 'Default Wallet';
        $description = $operation['description'] ?? null;
        $meta = $operation['meta'] ?? [];

        // Find the holder model
        $holder = $holderType::find($holderId);
        if (! $holder) {
            throw new ModelNotFoundException("Holder not found with type {$holderType} and ID {$holderId}");
        }

        $wallet = $this->walletManager->createWallet($holder, $currency, $name, $description, $meta);

        return [
            'index' => $index,
            'success' => true,
            'wallet' => $wallet,
            'holder_type' => $holderType,
            'holder_id' => $holderId,
            'currency' => $currency,
            'name' => $name,
        ];
    }

    /**
     * Process update balance operation
     */
    protected function processUpdateBalanceOperation(array $operation, int $index): array
    {
        $this->validateSingleOperation($operation, ['wallet_id', 'balance_type', 'amount']);

        $wallet = $this->getWalletById($operation['wallet_id']);
        $balanceType = BalanceType::tryFrom($operation['balance_type']);
        $amount = $operation['amount'];
        $description = $operation['description'] ?? 'Balance update';

        if (! $balanceType) {
            throw new \InvalidArgumentException("Invalid balance type: {$operation['balance_type']}");
        }

        // Create a credit or debit transaction instead of using protected updateBalance
        $transaction = $amount >= 0
            ? $wallet->credit(abs($amount), $balanceType, ['description' => $description])
            : $wallet->debit(abs($amount), $balanceType, ['description' => $description]);

        return [
            'index' => $index,
            'success' => true,
            'transaction' => $transaction,
            'wallet_id' => $wallet->id,
            'balance_type' => $balanceType->value,
            'amount' => $amount,
        ];
    }

    /**
     * Process transaction with validation
     */
    protected function processTransactionWithValidation(array $operation, int $index): array
    {
        $this->validateTransactionOperation($operation);

        $result = $this->executeTransactionOperation($operation);

        return [
            'index' => $index,
            'success' => true,
            'transaction' => $result,
            'operation' => $operation,
        ];
    }

    /**
     * Bulk operations for pending transactions
     */
    public function bulkPendingOperations(array $operations): array
    {
        return $this->executeBulkOperation('pending', $operations, false);
    }

    /**
     * Bulk operations for confirming transactions
     */
    public function bulkConfirmOperations(array $operations): array
    {
        return $this->executeBulkOperation('confirm', $operations, false);
    }

    /**
     * Validate bulk operations
     */
    protected function validateBulkOperations(array $operations, string $operationType): void
    {
        if (empty($operations)) {
            throw new BulkOperationException("No operations provided for {$operationType}");
        }

        $maxBatchSize = config('multi-wallet.bulk_operations.max_batch_size', 1000);
        if (count($operations) > $maxBatchSize) {
            throw new BulkOperationException("Batch size exceeds maximum allowed: {$maxBatchSize}");
        }
    }

    /**
     * Validate single operation
     */
    protected function validateSingleOperation(array $operation, array $requiredFields): void
    {
        foreach ($requiredFields as $field) {
            if (! isset($operation[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }
    }

    /**
     * Get wallet by ID
     */
    protected function getWalletById(int $walletId): Wallet
    {
        $wallet = Wallet::find($walletId);

        if (! $wallet) {
            throw new ModelNotFoundException("Wallet not found with ID: {$walletId}");
        }

        return $wallet;
    }

    /**
     * Validate transaction operation
     */
    protected function validateTransactionOperation(array $operation): void
    {
        $requiredFields = ['wallet_id', 'type', 'amount'];
        $this->validateSingleOperation($operation, $requiredFields);

        $validTypes = ['credit', 'debit', 'transfer', 'freeze', 'unfreeze'];
        if (! in_array($operation['type'], $validTypes)) {
            throw new \InvalidArgumentException("Invalid transaction type: {$operation['type']}");
        }
    }

    /**
     * Execute transaction operation
     */
    protected function executeTransactionOperation(array $operation)
    {
        $wallet = $this->getWalletById($operation['wallet_id']);
        $type = $operation['type'];
        $amount = $operation['amount'];
        $balanceType = BalanceType::tryFrom($operation['balance_type'] ?? 'available') ?? BalanceType::AVAILABLE;
        $meta = $operation['meta'] ?? [];

        switch ($type) {
            case 'credit':
                return $wallet->credit($amount, $balanceType, $meta);
            case 'debit':
                if (! $wallet->canDebit($amount, $balanceType)) {
                    throw new \Exception("Insufficient funds for wallet {$wallet->id}");
                }

                return $wallet->debit($amount, $balanceType, $meta);
            case 'freeze':
                return $wallet->freeze($amount, $meta['description'] ?? '');
            case 'unfreeze':
                return $wallet->unfreeze($amount, $meta['description'] ?? '');
            default:
                throw new \InvalidArgumentException("Unsupported transaction type: {$type}");
        }
    }
}
