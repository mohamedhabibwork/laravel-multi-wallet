<?php

namespace HWallet\LaravelMultiWallet\Services;

use HWallet\LaravelMultiWallet\Attributes\WalletOperation;
use HWallet\LaravelMultiWallet\Contracts\ExchangeRateProviderInterface;
use HWallet\LaravelMultiWallet\Contracts\WalletConfigurationInterface;
use HWallet\LaravelMultiWallet\Enums\BalanceType;
use HWallet\LaravelMultiWallet\Enums\TransactionType;
use HWallet\LaravelMultiWallet\Enums\TransferStatus;
use HWallet\LaravelMultiWallet\Exceptions\InsufficientFundsException;
use HWallet\LaravelMultiWallet\Exceptions\WalletNotFoundException;
use HWallet\LaravelMultiWallet\Models\Transaction;
use HWallet\LaravelMultiWallet\Models\Transfer;
use HWallet\LaravelMultiWallet\Models\Wallet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;

class WalletManager
{
    use Macroable;

    protected WalletConfigurationInterface $config;

    protected ExchangeRateProviderInterface $exchangeRateProvider;

    public function __construct(WalletConfigurationInterface $config)
    {
        $this->config = $config;
        $this->exchangeRateProvider = $config->getExchangeRateProvider();
    }

    /**
     * Create a new wallet for a model
     */
    #[WalletOperation('create_wallet', description: 'Create a new wallet for a model', requiresValidation: true, fireEvents: true)]
    public function create(
        Model $holder,
        string $currency,
        ?string $name = null,
        array $attributes = []
    ): Wallet {
        $currency = strtoupper($currency);

        // Validate currency
        if (! $this->exchangeRateProvider->supportsCurrency($currency)) {
            throw new \InvalidArgumentException("Unsupported currency: {$currency}");
        }

        // Check uniqueness if enabled
        if ($this->config->isUniquenessEnabled()) {
            $existing = $this->findExistingWallet($holder, $currency, $name);
            if ($existing) {
                throw new \InvalidArgumentException('Wallet already exists for this currency and name combination');
            }
        }

        $walletData = array_merge([
            'holder_type' => get_class($holder),
            'holder_id' => $holder->getKey(),
            'currency' => $currency,
            'name' => $name,
            'description' => null,
            'meta' => [],
            'balance_pending' => 0,
            'balance_available' => 0,
            'balance_frozen' => 0,
            'balance_trial' => 0,
        ], $attributes);

        // Generate slug if not provided
        if (empty($walletData['slug'])) {
            $tempWallet = new Wallet($walletData);
            $walletData['slug'] = Wallet::generateUniqueSlug($tempWallet);
        }

        // Create wallet through the model to ensure observers are called
        $wallet = new Wallet($walletData);
        $wallet->save();

        return $wallet;
    }

    /**
     * Find an existing wallet
     */
    protected function findExistingWallet(Model $holder, string $currency, ?string $name = null): ?Wallet
    {
        $query = Wallet::where('holder_type', get_class($holder))
            ->where('holder_id', $holder->getKey())
            ->where('currency', $currency);

        if ($name !== null) {
            $query->where('name', $name);
        } else {
            $query->whereNull('name');
        }

        return $query->first();
    }

    /**
     * Transfer funds between wallets
     */
    #[WalletOperation('transfer', description: 'Transfer funds between wallets', requiresValidation: true, fireEvents: true)]
    public function transfer(
        Wallet $fromWallet,
        Wallet $toWallet,
        float $amount,
        array $options = []
    ): Transfer {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Transfer amount must be positive');
        }

        $fee = $options['fee'] ?? 0;
        $discount = $options['discount'] ?? 0;
        $description = $options['description'] ?? 'Transfer';
        $fromBalanceType = BalanceType::tryFrom($options['from_balance_type'] ?? 'available') ?? BalanceType::AVAILABLE;
        $toBalanceType = BalanceType::tryFrom($options['to_balance_type'] ?? 'available') ?? BalanceType::AVAILABLE;

        // Validate sufficient funds (including fee)
        $totalDeduction = $amount + $fee - $discount;
        if (! $fromWallet->canDebit($totalDeduction, $fromBalanceType)) {
            throw new InsufficientFundsException('Insufficient funds for transfer including fees');
        }

        // Calculate conversion if different currencies
        $transferAmount = $amount;
        if ($fromWallet->currency !== $toWallet->currency) {
            $transferAmount = $this->exchangeRateProvider->convert(
                $amount,
                $fromWallet->currency,
                $toWallet->currency
            );
        }

        return DB::transaction(function () use (
            $fromWallet,
            $toWallet,
            $amount,
            $transferAmount,
            $fee,
            $discount,
            $description,
            $fromBalanceType,
            $toBalanceType,
            $options
        ) {
            $status = $options['status'] ?? TransferStatus::CONFIRMED;
            $withdrawTransaction = null;
            $depositTransaction = null;

            // Only move funds if status is CONFIRMED or PAID
            if (in_array($status, [TransferStatus::CONFIRMED, TransferStatus::PAID])) {
                // Create withdraw transaction
                $withdrawTransaction = $fromWallet->debit(
                    $amount + $fee - $discount,
                    $fromBalanceType,
                    [
                        'description' => $description,
                        'transfer' => true,
                        'fee' => $fee,
                        'discount' => $discount,
                    ]
                );

                // Create deposit transaction
                $depositTransaction = $toWallet->credit(
                    $transferAmount,
                    $toBalanceType,
                    [
                        'description' => $description,
                        'transfer' => true,
                    ]
                );
            }

            // Create transfer record
            $transfer = Transfer::create([
                'from_type' => $fromWallet->holder_type,
                'from_id' => $fromWallet->holder_id,
                'to_type' => $toWallet->holder_type,
                'to_id' => $toWallet->holder_id,
                'status' => $status,
                'status_last_changed_at' => now(),
                'deposit_id' => $depositTransaction?->id,
                'withdraw_id' => $withdrawTransaction?->id,
                'discount' => $discount,
                'fee' => $fee,
                'uuid' => Str::uuid()->toString(),
            ]);

            return $transfer;
        });
    }

    /**
     * Transfer with fee calculation
     */
    public function transferWithFee(
        Wallet $fromWallet,
        Wallet $toWallet,
        float $amount,
        float $fee,
        string $description = ''
    ): Transfer {
        return $this->transfer($fromWallet, $toWallet, $amount, [
            'fee' => $fee,
            'description' => $description,
        ]);
    }

    /**
     * Batch transfer to multiple recipients
     */
    public function batchTransfer(
        Wallet $fromWallet,
        array $recipients,
        array $options = []
    ): array {
        $transfers = [];
        $description = $options['description'] ?? 'Batch transfer';

        DB::transaction(function () use ($fromWallet, $recipients, $description, &$transfers) {
            foreach ($recipients as $recipient) {
                $toWallet = $recipient['wallet'];
                $amount = $recipient['amount'];
                $recipientOptions = array_merge($recipient, ['description' => $description]);

                $transfers[] = $this->transfer($fromWallet, $toWallet, $amount, $recipientOptions);
            }
        });

        return $transfers;
    }

    /**
     * Get wallet by slug
     */
    public function getBySlug(string $slug): Wallet
    {
        $wallet = Wallet::where('slug', $slug)->first();

        if (! $wallet) {
            throw new WalletNotFoundException("Wallet with slug '{$slug}' not found");
        }

        return $wallet;
    }

    /**
     * Get or create wallet for a holder
     */
    public function getOrCreate(
        Model $holder,
        string $currency,
        ?string $name = null,
        array $attributes = []
    ): Wallet {
        $existing = $this->findExistingWallet($holder, $currency, $name);

        if ($existing) {
            return $existing;
        }

        return $this->create($holder, $currency, $name, $attributes);
    }

    /**
     * Calculate transfer fee
     */
    public function calculateFee(float $amount, array $feeConfig = []): float
    {
        $config = array_merge($this->config->getFeeCalculationSettings(), $feeConfig);

        if ($config['percentage_based'] ?? false) {
            $percentage = $config['fee_percentage'] ?? 0;

            return $amount * ($percentage / 100);
        }

        return $config['default_fee'] ?? 0;
    }

    /**
     * Get transaction history for a wallet
     */
    public function getTransactionHistory(Wallet $wallet, array $filters = [])
    {
        $query = $wallet->transactions();

        if (isset($filters['type'])) {
            $query->byType(TransactionType::tryFrom($filters['type']));
        }

        if (isset($filters['balance_type'])) {
            $query->byBalanceType(BalanceType::tryFrom($filters['balance_type']));
        }

        if (isset($filters['confirmed'])) {
            if ($filters['confirmed']) {
                $query->confirmed();
            } else {
                $query->pending();
            }
        }

        if (isset($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }

        if (isset($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date']);
        }

        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Get balance summary for a holder
     */
    public function getBalanceSummary(Model $holder, ?string $currency = null): array
    {
        /** @var \Illuminate\Database\Eloquent\Relations\MorphMany $query */
        $query = $holder->wallets();

        if ($currency) {
            $query->where('currency', $currency);
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, Wallet> $wallets */
        $wallets = $query->get();
        $summary = [];

        foreach ($wallets as $wallet) {
            /** @var Wallet $wallet */
            $currencyKey = $wallet->currency;

            if (! isset($summary[$currencyKey])) {
                $summary[$currencyKey] = [
                    'currency' => $currencyKey,
                    'total_wallets' => 0,
                    'total_balance' => 0,
                    'available_balance' => 0,
                    'pending_balance' => 0,
                    'frozen_balance' => 0,
                    'trial_balance' => 0,
                ];
            }

            $summary[$currencyKey]['total_wallets']++;
            $summary[$currencyKey]['total_balance'] += $wallet->getTotalBalance();
            $summary[$currencyKey]['available_balance'] += $wallet->getBalance(BalanceType::AVAILABLE);
            $summary[$currencyKey]['pending_balance'] += $wallet->getBalance(BalanceType::PENDING);
            $summary[$currencyKey]['frozen_balance'] += $wallet->getBalance(BalanceType::FROZEN);
            $summary[$currencyKey]['trial_balance'] += $wallet->getBalance(BalanceType::TRIAL);
        }

        return array_values($summary);
    }

    /**
     * Validate transaction limits
     */
    public function validateTransactionLimits(float $amount): bool
    {
        $limits = $this->config->getTransactionLimits();

        if (isset($limits['min_amount']) && $amount < $limits['min_amount']) {
            throw new \InvalidArgumentException("Amount below minimum limit of {$limits['min_amount']}");
        }

        if (isset($limits['max_amount']) && $amount > $limits['max_amount']) {
            throw new \InvalidArgumentException("Amount exceeds maximum limit of {$limits['max_amount']}");
        }

        return true;
    }

    /**
     * Get the configuration instance
     */
    public function getConfiguration(): WalletConfigurationInterface
    {
        return $this->config;
    }

    /**
     * Get the exchange rate provider
     */
    public function getExchangeRateProvider(): ExchangeRateProviderInterface
    {
        return $this->exchangeRateProvider;
    }

    /**
     * Create wallet (alias for create method)
     */
    public function createWallet(Model $holder, string $currency, ?string $name = null, ?string $description = null, array $meta = []): Wallet
    {
        $attributes = [];
        if ($description !== null) {
            $attributes['description'] = $description;
        }
        if (! empty($meta)) {
            $attributes['meta'] = $meta;
        }

        return $this->create($holder, $currency, $name, $attributes);
    }

    /**
     * Find wallet by ID
     */
    public function findWallet(int $id): ?Wallet
    {
        return Wallet::find($id);
    }

    /**
     * Find wallet by holder and currency
     */
    public function findWalletByHolderAndCurrency(Model $holder, string $currency, ?string $name = null): ?Wallet
    {
        return $this->findExistingWallet($holder, $currency, $name);
    }

    /**
     * Get or create wallet
     */
    public function getOrCreateWallet(Model $holder, string $currency, ?string $name = null, array $attributes = []): Wallet
    {
        return $this->getOrCreate($holder, $currency, $name, $attributes);
    }

    /**
     * Credit a wallet
     */
    public function creditWallet(Wallet $wallet, float $amount, string $balanceType = 'available', array $meta = []): Transaction
    {
        $type = BalanceType::tryFrom($balanceType) ?? BalanceType::AVAILABLE;

        return $wallet->credit($amount, $type, $meta);
    }

    /**
     * Debit a wallet
     */
    public function debitWallet(Wallet $wallet, float $amount, string $balanceType = 'available', array $meta = []): Transaction
    {
        $type = BalanceType::tryFrom($balanceType) ?? BalanceType::AVAILABLE;

        return $wallet->debit($amount, $type, $meta);
    }

    /**
     * Confirm a transfer
     */
    public function confirmTransfer(Transfer $transfer): bool
    {
        if ($transfer->status === TransferStatus::CONFIRMED) {
            return true;
        }

        $transfer->markAsConfirmed();

        return true;
    }

    /**
     * Reject a transfer
     */
    public function rejectTransfer(Transfer $transfer, string $reason = ''): bool
    {
        if ($transfer->status === TransferStatus::REJECTED) {
            return true;
        }

        $transfer->markAsRejected();

        // Reverse the transaction if it was already processed
        if ($transfer->withdraw_id && $transfer->deposit_id) {
            $withdrawTransaction = Transaction::find($transfer->withdraw_id);
            $depositTransaction = Transaction::find($transfer->deposit_id);

            if ($withdrawTransaction && $depositTransaction) {
                // Reverse withdraw (credit back)
                $withdrawBalanceType = $withdrawTransaction->balance_type;

                $withdrawTransaction->wallet->credit(
                    $withdrawTransaction->amount,
                    $withdrawBalanceType,
                    ['reversal' => true, 'original_transaction_id' => $withdrawTransaction->id]
                );

                // Reverse deposit (debit back)
                $depositBalanceType = $depositTransaction->balance_type;

                $depositTransaction->wallet->debit(
                    $depositTransaction->amount,
                    $depositBalanceType,
                    ['reversal' => true, 'original_transaction_id' => $depositTransaction->id]
                );
            }
        }

        return true;
    }

    /**
     * Get wallet balance
     */
    public function getWalletBalance(Wallet $wallet, string $balanceType = 'total'): float
    {
        if ($balanceType === 'total') {
            return $wallet->getTotalBalance();
        }

        $type = BalanceType::tryFrom($balanceType) ?? BalanceType::AVAILABLE;

        return $wallet->getBalance($type);
    }

    /**
     * Get wallet history (alias for transaction history)
     */
    public function getWalletHistory(Wallet $wallet, array $filters = [])
    {
        return $this->getTransactionHistory($wallet, $filters)->get();
    }

    /**
     * Get wallet transactions
     */
    public function getWalletTransactions(Wallet $wallet, array $filters = [])
    {
        return $this->getTransactionHistory($wallet, $filters)->get();
    }

    /**
     * Get wallet transfers
     */
    public function getWalletTransfers(Wallet $wallet, string $direction = 'all')
    {
        $holderType = $wallet->holder_type;
        $holderId = $wallet->holder_id;

        $query = Transfer::query();

        if ($direction === 'outgoing') {
            $query->where('from_type', $holderType)->where('from_id', $holderId);
        } elseif ($direction === 'incoming') {
            $query->where('to_type', $holderType)->where('to_id', $holderId);
        } else {
            $query->where(function ($q) use ($holderType, $holderId) {
                $q->where(function ($sq) use ($holderType, $holderId) {
                    $sq->where('from_type', $holderType)->where('from_id', $holderId);
                })->orWhere(function ($sq) use ($holderType, $holderId) {
                    $sq->where('to_type', $holderType)->where('to_id', $holderId);
                });
            });
        }

        return $query->get();
    }

    /**
     * Delete a wallet
     */
    public function deleteWallet(Wallet $wallet): bool
    {
        // Check if wallet has balance
        if ($wallet->getTotalBalance() > 0) {
            throw new \Exception('Cannot delete wallet with remaining balance');
        }

        return $wallet->delete();
    }

    /**
     * Freeze wallet amount
     */
    public function freezeWallet(Wallet $wallet, float $amount, string $description = ''): bool
    {
        try {
            $wallet->freeze($amount, $description);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Unfreeze wallet amount
     */
    public function unfreezeWallet(Wallet $wallet, float $amount, string $description = ''): bool
    {
        try {
            $wallet->unfreeze($amount, $description);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get bulk wallet manager
     */
    public function getBulkWalletManager(): \HWallet\LaravelMultiWallet\Services\BulkWalletManager
    {
        return app(\HWallet\LaravelMultiWallet\Services\BulkWalletManager::class);
    }

    /**
     * Execute bulk operations
     */
    #[WalletOperation('bulk_operations', description: 'Execute bulk wallet operations', requiresValidation: true, fireEvents: true)]
    public function executeBulkOperations(string $operationType, array $operations): array
    {
        $bulkManager = $this->getBulkWalletManager();

        return match ($operationType) {
            'credit' => $bulkManager->bulkCredit($operations),
            'debit' => $bulkManager->bulkDebit($operations),
            'transfer' => $bulkManager->bulkTransfer($operations),
            'freeze' => $bulkManager->bulkFreeze($operations),
            'unfreeze' => $bulkManager->bulkUnfreeze($operations),
            'create_wallets' => $bulkManager->bulkCreateWallets($operations),
            'update_balances' => $bulkManager->bulkUpdateBalances($operations),
            default => throw new \InvalidArgumentException("Unsupported bulk operation type: {$operationType}")
        };
    }

    /**
     * Get wallet configuration attributes from model
     */
    public function getWalletConfigurationFromModel(Model $model): array
    {
        if (method_exists($model, 'getWalletConfiguration')) {
            return $model->getWalletConfiguration();
        }

        return [];
    }

    /**
     * Create wallet with configuration attributes
     */
    #[WalletOperation('create_wallet_with_config', description: 'Create wallet using model configuration', requiresValidation: true, fireEvents: true)]
    public function createWalletWithConfig(Model $holder, ?string $currency = null, ?string $name = null): Wallet
    {
        $config = $this->getWalletConfigurationFromModel($holder);

        $currency = $currency ?? $config['default_currency'] ?? 'USD';
        $name = $name ?? $config['wallet_name'] ?? null;
        $attributes = $config['metadata'] ?? [];

        return $this->create($holder, $currency, $name, $attributes);
    }

    /**
     * Batch create wallets for multiple currencies
     */
    #[WalletOperation('batch_create_wallets', description: 'Create multiple wallets for different currencies', requiresValidation: true, fireEvents: true)]
    public function batchCreateWallets(Model $holder, array $currencies): array
    {
        $wallets = [];

        foreach ($currencies as $currency => $config) {
            if (is_numeric($currency)) {
                // Simple array of currencies
                $currency = $config;
                $config = [];
            }

            $name = $config['name'] ?? null;
            $attributes = $config['attributes'] ?? [];

            $wallets[$currency] = $this->create($holder, $currency, $name, $attributes);
        }

        return $wallets;
    }

    /**
     * Get wallet statistics
     */
    public function getWalletStatistics(Wallet $wallet): array
    {
        $transactions = $wallet->transactions();
        $transfers = Transfer::involving($wallet->holder)->get();

        return [
            'total_transactions' => $transactions->count(),
            'total_credits' => $transactions->where('type', TransactionType::CREDIT)->sum('amount'),
            'total_debits' => $transactions->where('type', TransactionType::DEBIT)->sum('amount'),
            'total_transfers_sent' => $transfers->where('from_type', $wallet->holder_type)
                ->where('from_id', $wallet->holder_id)->count(),
            'total_transfers_received' => $transfers->where('to_type', $wallet->holder_type)
                ->where('to_id', $wallet->holder_id)->count(),
            'current_balance' => $wallet->getTotalBalance(),
            'available_balance' => $wallet->getBalance(BalanceType::AVAILABLE),
            'pending_balance' => $wallet->getBalance(BalanceType::PENDING),
            'frozen_balance' => $wallet->getBalance(BalanceType::FROZEN),
            'trial_balance' => $wallet->getBalance(BalanceType::TRIAL),
        ];
    }

    /**
     * Reconcile wallet balances
     */
    #[WalletOperation('reconcile_wallet', description: 'Reconcile wallet balances with transactions', requiresValidation: true, fireEvents: true)]
    public function reconcileWallet(Wallet $wallet): array
    {
        $transactions = $wallet->transactions()->get();

        $calculatedBalances = [
            'available' => 0,
            'pending' => 0,
            'frozen' => 0,
            'trial' => 0,
        ];

        foreach ($transactions as $transaction) {
            /** @var \HWallet\LaravelMultiWallet\Models\Transaction $transaction */
            $balanceType = $transaction->balance_type->value;

            if ($transaction->type === TransactionType::CREDIT) {
                $calculatedBalances[$balanceType] += $transaction->amount;
            } else {
                $calculatedBalances[$balanceType] -= $transaction->amount;
            }
        }

        $currentBalances = [
            'available' => $wallet->getBalance(BalanceType::AVAILABLE),
            'pending' => $wallet->getBalance(BalanceType::PENDING),
            'frozen' => $wallet->getBalance(BalanceType::FROZEN),
            'trial' => $wallet->getBalance(BalanceType::TRIAL),
        ];

        $differences = [];
        foreach ($calculatedBalances as $type => $calculated) {
            $current = $currentBalances[$type];
            if (abs($calculated - $current) > 0.01) { // Allow for small floating point differences
                $differences[$type] = [
                    'calculated' => $calculated,
                    'current' => $current,
                    'difference' => $calculated - $current,
                ];
            }
        }

        return [
            'wallet_id' => $wallet->id,
            'is_balanced' => empty($differences),
            'differences' => $differences,
            'calculated_balances' => $calculatedBalances,
            'current_balances' => $currentBalances,
        ];
    }

    /**
     * Auto-reconcile wallet if differences found
     */
    #[WalletOperation('auto_reconcile_wallet', description: 'Automatically reconcile wallet balances', requiresValidation: true, fireEvents: true)]
    public function autoReconcileWallet(Wallet $wallet): bool
    {
        $reconciliation = $this->reconcileWallet($wallet);

        if ($reconciliation['is_balanced']) {
            return true;
        }

        // Update balances to match calculated values
        foreach ($reconciliation['differences'] as $type => $difference) {
            $wallet->update(["balance_{$type}" => $difference['calculated']]);
        }

        // Fire reconciliation event
        event(new \HWallet\LaravelMultiWallet\Events\WalletReconciled(
            $wallet,
            $reconciliation['differences'],
            $reconciliation['current_balances'], // corrections
            auth()->user()->name ?? 'system' // reconciledBy
        ));

        return true;
    }

    /**
     * Validate wallet operation using attributes
     */
    public function validateWalletOperation(string $method, array $params = []): bool
    {
        $reflection = new \ReflectionMethod($this, $method);
        $attributes = $reflection->getAttributes(\HWallet\LaravelMultiWallet\Attributes\WalletOperation::class);

        if (empty($attributes)) {
            return true; // No validation required
        }

        $operationConfig = $attributes[0]->newInstance();

        if ($operationConfig->requiresValidation) {
            // Perform validation based on operation type
            return $this->performOperationValidation($operationConfig->operation, $params);
        }

        return true;
    }

    /**
     * Perform operation validation
     */
    protected function performOperationValidation(string $operation, array $params): bool
    {
        // Implement validation logic based on operation type
        switch ($operation) {
            case 'create_wallet':
                return $this->validateCreateWallet($params);
            case 'transfer':
                return $this->validateTransfer($params);
            default:
                return true;
        }
    }

    /**
     * Validate create wallet operation
     */
    protected function validateCreateWallet(array $params): bool
    {
        // Implement create wallet validation
        return true;
    }

    /**
     * Validate transfer operation
     */
    protected function validateTransfer(array $params): bool
    {
        // Implement transfer validation
        return true;
    }
}
