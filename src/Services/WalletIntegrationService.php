<?php

namespace HWallet\LaravelMultiWallet\Services;

use HWallet\LaravelMultiWallet\Contracts\ValidatorInterface;
use HWallet\LaravelMultiWallet\Contracts\WalletInterface;
use HWallet\LaravelMultiWallet\Helpers\WalletHelpers;
use HWallet\LaravelMultiWallet\Models\Wallet;
use HWallet\LaravelMultiWallet\Repositories\WalletRepositoryInterface;
use HWallet\LaravelMultiWallet\Types\WalletTypes;
use HWallet\LaravelMultiWallet\Utils\WalletUtils;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Comprehensive wallet integration service
 * Provides a unified interface for all wallet operations
 */
class WalletIntegrationService
{
    public function __construct(
        private WalletManager $walletManager,
        private BulkWalletManager $bulkWalletManager,
        private WalletRepositoryInterface $repository,
        private ValidatorInterface $validator,
        private WalletHelpers $helpers,
        private WalletUtils $utils
    ) {}

    /**
     * Create a new wallet with full validation and type safety
     */
    public function createWallet(
        Model $holder,
        string $currency,
        ?string $name = null,
        array $metadata = [],
        array $configuration = []
    ): WalletInterface {
        // Validate input using type system
        $currencyType = WalletTypes::createCurrency($currency);
        $metadataType = WalletTypes::createWalletMetadata($metadata);
        $configType = WalletTypes::createWalletConfiguration($configuration);

        // Validate with comprehensive validator
        $validationResult = $this->validator->validateWalletCreation([
            'holder' => $holder,
            'currency' => $currency,
            'name' => $name,
            'metadata' => $metadata,
            'configuration' => $configuration
        ]);

        if (!$validationResult->isValid()) {
            throw new \InvalidArgumentException(
                'Wallet creation validation failed: ' . implode(', ', $validationResult->getErrors())
            );
        }

        return DB::transaction(function () use ($holder, $currencyType, $name, $metadataType, $configType) {
            $wallet = $this->walletManager->createWallet(
                $holder,
                $currencyType->getCode(),
                $name,
                $metadataType->getData()
            );

            // Apply configuration if provided
            if (!empty($configType->getConfig())) {
                $this->applyConfiguration($wallet, $configType);
            }

            // Log wallet creation
            Log::info('Wallet created successfully', [
                'wallet_id' => $wallet->id,
                'holder_type' => get_class($holder),
                'holder_id' => $holder->getKey(),
                'currency' => $currencyType->getCode(),
                'name' => $name
            ]);

            return $wallet;
        });
    }

    /**
     * Perform a safe transaction with validation and type checking
     */
    public function performTransaction(
        WalletInterface $wallet,
        string $type,
        float $amount,
        string $balanceType = 'available',
        array $metadata = []
    ): array {
        // Type safety
        $amountType = WalletTypes::createAmount($amount);
        $metadataType = WalletTypes::createTransactionMetadata($metadata);

        // Validate transaction
        $validationResult = $this->validator->validateTransaction([
            'wallet' => $wallet,
            'type' => $type,
            'amount' => $amount,
            'balance_type' => $balanceType,
            'metadata' => $metadata
        ]);

        if (!$validationResult->isValid()) {
            throw new \InvalidArgumentException(
                'Transaction validation failed: ' . implode(', ', $validationResult->getErrors())
            );
        }

        return DB::transaction(function () use ($wallet, $type, $amountType, $balanceType, $metadataType) {
            $transaction = match ($type) {
                'credit' => $wallet->credit($amountType->getValue(), $balanceType, $metadataType->getData()),
                'debit' => $wallet->debit($amountType->getValue(), $balanceType, $metadataType->getData()),
                default => throw new \InvalidArgumentException("Unsupported transaction type: {$type}")
            };

            // Generate transaction summary
            $summary = $this->generateTransactionSummary($wallet, $transaction);

            // Run integrity check
            $integrityResult = $this->utils->validateWalletIntegrity($wallet);
            
            if (!$integrityResult['valid']) {
                Log::warning('Wallet integrity check failed after transaction', [
                    'wallet_id' => $wallet->id,
                    'transaction_id' => $transaction->id,
                    'issues' => $integrityResult['issues']
                ]);
            }

            return [
                'transaction' => $transaction,
                'summary' => $summary,
                'integrity' => $integrityResult,
                'wallet_state' => $this->getWalletState($wallet)
            ];
        });
    }

    /**
     * Get comprehensive wallet dashboard data
     */
    public function getWalletDashboard(Model $holder): array
    {
        $wallets = $this->repository->getWalletsWithTransactions($holder);
        $statistics = $this->repository->getStatistics($holder);
        
        $dashboard = [
            'wallets' => [],
            'statistics' => $statistics,
            'health_check' => [],
            'recommendations' => []
        ];

        foreach ($wallets as $wallet) {
            $walletData = [
                'wallet' => $wallet,
                'formatted_balances' => $this->getFormattedBalances($wallet),
                'recent_transactions' => $wallet->transactions,
                'performance_metrics' => $this->utils->getWalletPerformanceMetrics($wallet),
                'health_status' => $this->utils->checkWalletHealth($wallet)
            ];

            $dashboard['wallets'][] = $walletData;
            $dashboard['health_check'][] = $walletData['health_status'];
        }

        // Generate recommendations
        $dashboard['recommendations'] = $this->generateRecommendations($dashboard['wallets']);

        return $dashboard;
    }

    /**
     * Bulk operations with progress tracking
     */
    public function performBulkOperation(
        array $wallets,
        string $operation,
        array $parameters = [],
        callable $progressCallback = null
    ): array {
        $results = [];
        $errors = [];
        $processed = 0;
        $total = count($wallets);

        foreach ($wallets as $wallet) {
            try {
                $result = match ($operation) {
                    'update_metadata' => $this->updateWalletMetadata($wallet, $parameters['metadata'] ?? []),
                    'freeze' => $wallet->freeze($parameters['amount'] ?? 0, $parameters['reason'] ?? ''),
                    'unfreeze' => $wallet->unfreeze($parameters['amount'] ?? 0, $parameters['reason'] ?? ''),
                    'reconcile' => $this->reconcileWallet($wallet),
                    default => throw new \InvalidArgumentException("Unsupported bulk operation: {$operation}")
                };

                $results[] = [
                    'wallet_id' => $wallet->id,
                    'success' => true,
                    'result' => $result
                ];

            } catch (\Exception $e) {
                $errors[] = [
                    'wallet_id' => $wallet->id,
                    'error' => $e->getMessage()
                ];
            }

            $processed++;
            
            if ($progressCallback) {
                $progressCallback($processed, $total);
            }
        }

        return [
            'total' => $total,
            'processed' => $processed,
            'successful' => count($results),
            'failed' => count($errors),
            'results' => $results,
            'errors' => $errors
        ];
    }

    /**
     * Generate comprehensive reports
     */
    public function generateReport(Model $holder, string $type, array $options = []): array
    {
        return match ($type) {
            'summary' => $this->utils->generateSummaryReport($holder, $options),
            'detailed' => $this->utils->generateDetailedReport($holder, $options),
            'audit' => $this->utils->generateAuditReport($holder, $options),
            'performance' => $this->utils->generatePerformanceReport($holder, $options),
            default => throw new \InvalidArgumentException("Unsupported report type: {$type}")
        };
    }

    /**
     * Search wallets with advanced criteria
     */
    public function searchWallets(array $criteria, array $options = []): array
    {
        // Validate search criteria
        $validationResult = $this->validator->validateSearchCriteria($criteria);
        
        if (!$validationResult->isValid()) {
            throw new \InvalidArgumentException(
                'Search criteria validation failed: ' . implode(', ', $validationResult->getErrors())
            );
        }

        $wallets = $this->repository->search($criteria);
        
        $results = [];
        foreach ($wallets as $wallet) {
            $results[] = [
                'wallet' => $wallet,
                'formatted_balance' => $this->helpers->formatAmount($wallet->getTotalBalance(), $wallet->currency),
                'status' => $this->getWalletStatus($wallet),
                'last_activity' => $wallet->transactions()->latest()->first()?->created_at
            ];
        }

        return [
            'total' => count($results),
            'results' => $results,
            'criteria' => $criteria
        ];
    }

    /**
     * Get wallet analytics
     */
    public function getWalletAnalytics(WalletInterface $wallet, array $options = []): array
    {
        $period = $options['period'] ?? 30; // days
        $includeProjections = $options['include_projections'] ?? false;

        $analytics = [
            'basic_stats' => $this->utils->getWalletStats($wallet),
            'transaction_analysis' => $this->analyzeTransactionPatterns($wallet, $period),
            'balance_history' => $this->getBalanceHistory($wallet, $period),
            'performance_metrics' => $this->utils->getWalletPerformanceMetrics($wallet)
        ];

        if ($includeProjections) {
            $analytics['projections'] = $this->generateProjections($wallet, $analytics);
        }

        return $analytics;
    }

    /**
     * Helper methods
     */
    private function applyConfiguration(WalletInterface $wallet, $configType): void
    {
        // Apply configuration settings to wallet
        $config = $configType->getConfig();
        
        if (!empty($config)) {
            $wallet->update(['meta' => array_merge($wallet->meta ?? [], ['config' => $config])]);
        }
    }

    private function generateTransactionSummary(WalletInterface $wallet, $transaction): array
    {
        return [
            'transaction_id' => $transaction->id,
            'type' => $transaction->type,
            'amount' => $this->helpers->formatAmount($transaction->amount, $wallet->currency),
            'balance_after' => $this->helpers->formatAmount($wallet->getTotalBalance(), $wallet->currency),
            'timestamp' => $transaction->created_at->toISOString()
        ];
    }

    private function getWalletState(WalletInterface $wallet): array
    {
        $balanceSummary = WalletTypes::createBalanceSummary([
            'available' => $wallet->getBalance('available'),
            'pending' => $wallet->getBalance('pending'),
            'frozen' => $wallet->getBalance('frozen'),
            'trial' => $wallet->getBalance('trial'),
            'total' => $wallet->getTotalBalance()
        ]);

        return [
            'id' => $wallet->id,
            'currency' => $wallet->currency,
            'balances' => $balanceSummary->getAllBalances(),
            'formatted_balances' => $this->getFormattedBalances($wallet),
            'status' => $this->getWalletStatus($wallet)
        ];
    }

    private function getFormattedBalances(WalletInterface $wallet): array
    {
        return [
            'available' => $this->helpers->formatAmount($wallet->getBalance('available'), $wallet->currency),
            'pending' => $this->helpers->formatAmount($wallet->getBalance('pending'), $wallet->currency),
            'frozen' => $this->helpers->formatAmount($wallet->getBalance('frozen'), $wallet->currency),
            'trial' => $this->helpers->formatAmount($wallet->getBalance('trial'), $wallet->currency),
            'total' => $this->helpers->formatAmount($wallet->getTotalBalance(), $wallet->currency)
        ];
    }

    private function getWalletStatus(WalletInterface $wallet): string
    {
        if ($wallet->getBalance('frozen') > 0) {
            return 'partially_frozen';
        }

        if ($wallet->getBalance('pending') > 0) {
            return 'has_pending';
        }

        if ($wallet->getTotalBalance() > 0) {
            return 'active';
        }

        return 'empty';
    }

    private function generateRecommendations(array $wallets): array
    {
        $recommendations = [];

        foreach ($wallets as $walletData) {
            $wallet = $walletData['wallet'];
            $health = $walletData['health_status'];

            if (!$health['healthy']) {
                $recommendations[] = [
                    'wallet_id' => $wallet->id,
                    'type' => 'health_issue',
                    'message' => 'Wallet health check failed',
                    'issues' => $health['issues']
                ];
            }

            if ($wallet->getBalance('pending') > 0) {
                $recommendations[] = [
                    'wallet_id' => $wallet->id,
                    'type' => 'pending_balance',
                    'message' => 'Consider confirming pending transactions',
                    'amount' => $wallet->getBalance('pending')
                ];
            }
        }

        return $recommendations;
    }

    private function updateWalletMetadata(WalletInterface $wallet, array $metadata): bool
    {
        $metadataType = WalletTypes::createWalletMetadata($metadata);
        return $wallet->update(['meta' => array_merge($wallet->meta ?? [], $metadataType->getData())]);
    }

    private function reconcileWallet(WalletInterface $wallet): array
    {
        return $this->utils->reconcileWallet($wallet);
    }

    private function analyzeTransactionPatterns(WalletInterface $wallet, int $days): array
    {
        $transactions = $wallet->transactions()
            ->where('created_at', '>=', now()->subDays($days))
            ->get();

        return [
            'total_transactions' => $transactions->count(),
            'credits' => $transactions->where('type', 'credit')->count(),
            'debits' => $transactions->where('type', 'debit')->count(),
            'average_amount' => $transactions->avg('amount'),
            'total_volume' => $transactions->sum('amount')
        ];
    }

    private function getBalanceHistory(WalletInterface $wallet, int $days): array
    {
        // This would typically query a balance history table
        // For now, return current balance
        return [
            'current' => $wallet->getTotalBalance(),
            'history' => [] // Would be populated from balance history table
        ];
    }

    private function generateProjections(WalletInterface $wallet, array $analytics): array
    {
        $transactionAnalysis = $analytics['transaction_analysis'];
        $avgDaily = $transactionAnalysis['total_volume'] / 30;

        return [
            'projected_30_days' => $wallet->getTotalBalance() + ($avgDaily * 30),
            'projected_90_days' => $wallet->getTotalBalance() + ($avgDaily * 90),
            'confidence' => $transactionAnalysis['total_transactions'] > 10 ? 'high' : 'low'
        ];
    }
} 