<?php

namespace HWallet\LaravelMultiWallet\Services;

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

class WalletManager
{
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
}
