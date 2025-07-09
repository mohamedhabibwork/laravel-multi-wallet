<?php

use HWallet\LaravelMultiWallet\Utils\WalletUtils;
use HWallet\LaravelMultiWallet\Models\Wallet;
use HWallet\LaravelMultiWallet\Tests\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->wallet = $this->user->createWallet('USD', 'Test Wallet');
    $this->wallet->credit(1000);
    $this->utils = app(WalletUtils::class);
});

describe('Wallet Debugging', function () {
    it('can debug wallet state', function () {
        $debug = $this->utils->debugWallet($this->wallet);
        
        expect($debug)->toHaveKeys([
            'wallet_info',
            'balances',
            'recent_transactions',
            'metadata',
            'configuration',
            'relationships'
        ]);
        
        expect($debug['wallet_info']['id'])->toBe($this->wallet->id);
        expect($debug['balances']['available'])->toBe(1000.0);
    });

    it('can get wallet audit trail', function () {
        // Create some transactions
        $this->wallet->debit(100);
        $this->wallet->credit(50);
        
        $audit = $this->utils->getWalletAuditTrail($this->wallet);
        
        expect($audit)->toHaveKeys(['transactions', 'summary']);
        expect($audit['transactions'])->toHaveCount(3); // Initial credit + debit + credit
        expect($audit['summary']['total_transactions'])->toBe(3);
    });

    it('can export wallet data', function () {
        $export = $this->utils->exportWalletData($this->wallet);
        
        expect($export)->toHaveKeys([
            'wallet',
            'transactions',
            'transfers',
            'metadata',
            'exported_at'
        ]);
        
        expect($export['wallet']['id'])->toBe($this->wallet->id);
        expect($export['transactions'])->toHaveCount(1); // Initial credit
    });
});

describe('Wallet Validation', function () {
    it('can validate wallet integrity', function () {
        $result = $this->utils->validateWalletIntegrity($this->wallet);
        
        expect($result)->toHaveKeys(['valid', 'issues', 'warnings']);
        expect($result['valid'])->toBeTrue();
        expect($result['issues'])->toBeEmpty();
    });

    it('can detect integrity issues', function () {
        // Manually corrupt the wallet balance
        $this->wallet->update(['balance_available' => 999]);
        
        $result = $this->utils->validateWalletIntegrity($this->wallet);
        
        expect($result['valid'])->toBeFalse();
        expect($result['issues'])->not->toBeEmpty();
    });

    it('can reconcile wallet', function () {
        $result = $this->utils->reconcileWallet($this->wallet);
        
        expect($result)->toHaveKeys(['reconciled', 'changes', 'summary']);
        expect($result['reconciled'])->toBeTrue();
    });
});

describe('Wallet Health Check', function () {
    it('can check wallet health', function () {
        $health = $this->utils->checkWalletHealth($this->wallet);
        
        expect($health)->toHaveKeys(['healthy', 'score', 'issues', 'recommendations']);
        expect($health['healthy'])->toBeTrue();
        expect($health['score'])->toBeGreaterThan(80);
    });

    it('can detect health issues', function () {
        // Create a wallet with potential issues
        $problematicWallet = $this->user->createWallet('USD', 'Problem Wallet');
        $problematicWallet->credit(100);
        $problematicWallet->freeze(100); // Freeze all funds
        
        $health = $this->utils->checkWalletHealth($problematicWallet);
        
        expect($health['healthy'])->toBeFalse();
        expect($health['score'])->toBeLessThan(80);
        expect($health['issues'])->not->toBeEmpty();
    });
});

describe('Performance Metrics', function () {
    it('can get wallet performance metrics', function () {
        // Create some transaction history
        $this->wallet->debit(100);
        $this->wallet->credit(200);
        $this->wallet->debit(50);
        
        $metrics = $this->utils->getWalletPerformanceMetrics($this->wallet);
        
        expect($metrics)->toHaveKeys([
            'transaction_count',
            'average_transaction_amount',
            'total_volume',
            'balance_velocity',
            'activity_score'
        ]);
        
        expect($metrics['transaction_count'])->toBe(4); // Including initial credit
        expect($metrics['total_volume'])->toBe(1350.0); // 1000 + 100 + 200 + 50
    });

    it('can calculate wallet statistics', function () {
        $stats = $this->utils->getWalletStats($this->wallet);
        
        expect($stats)->toHaveKeys([
            'total_transactions',
            'total_credits',
            'total_debits',
            'average_transaction_amount',
            'largest_transaction',
            'smallest_transaction',
            'balance_history'
        ]);
        
        expect($stats['total_transactions'])->toBe(1);
        expect($stats['total_credits'])->toBe(1000.0);
        expect($stats['total_debits'])->toBe(0.0);
    });
});

describe('Reporting', function () {
    it('can generate summary report', function () {
        $report = $this->utils->generateSummaryReport($this->user);
        
        expect($report)->toHaveKeys([
            'user_info',
            'wallet_summary',
            'balance_summary',
            'recent_activity',
            'generated_at'
        ]);
        
        expect($report['wallet_summary']['total_wallets'])->toBe(1);
        expect($report['balance_summary']['total_balance'])->toBe(1000.0);
    });

    it('can generate detailed report', function () {
        $report = $this->utils->generateDetailedReport($this->user, [
            'include_transactions' => true,
            'include_transfers' => true
        ]);
        
        expect($report)->toHaveKeys([
            'user_info',
            'wallets',
            'transactions',
            'transfers',
            'analytics',
            'generated_at'
        ]);
        
        expect($report['wallets'])->toHaveCount(1);
        expect($report['transactions'])->toHaveCount(1);
    });

    it('can generate audit report', function () {
        $report = $this->utils->generateAuditReport($this->user, ['days' => 30]);
        
        expect($report)->toHaveKeys([
            'period',
            'user_info',
            'audit_trail',
            'summary',
            'compliance_check',
            'generated_at'
        ]);
        
        expect($report['period']['days'])->toBe(30);
        expect($report['audit_trail'])->not->toBeEmpty();
    });

    it('can generate performance report', function () {
        $report = $this->utils->generatePerformanceReport($this->user);
        
        expect($report)->toHaveKeys([
            'user_info',
            'performance_metrics',
            'wallet_health',
            'recommendations',
            'generated_at'
        ]);
        
        expect($report['performance_metrics'])->toHaveKeys([
            'total_volume',
            'transaction_frequency',
            'average_balance',
            'activity_score'
        ]);
    });
});

describe('Data Analysis', function () {
    it('can analyze transaction patterns', function () {
        // Create varied transaction history
        $this->wallet->credit(100);
        $this->wallet->debit(50);
        $this->wallet->credit(200);
        $this->wallet->debit(75);
        
        $patterns = $this->utils->analyzeTransactionPatterns($this->wallet, 30);
        
        expect($patterns)->toHaveKeys([
            'transaction_frequency',
            'amount_patterns',
            'time_patterns',
            'balance_trends'
        ]);
        
        expect($patterns['transaction_frequency']['daily_average'])->toBeGreaterThan(0);
    });

    it('can detect anomalies', function () {
        // Create normal transactions
        $this->wallet->credit(100);
        $this->wallet->debit(50);
        
        // Create an anomalous transaction
        $this->wallet->credit(10000); // Unusually large amount
        
        $anomalies = $this->utils->detectAnomalies($this->wallet);
        
        expect($anomalies)->toHaveKeys(['detected', 'anomalies', 'score']);
        expect($anomalies['detected'])->toBeTrue();
        expect($anomalies['anomalies'])->not->toBeEmpty();
    });
});

describe('Bulk Operations', function () {
    it('can perform bulk wallet operations', function () {
        $wallet2 = $this->user->createWallet('EUR', 'Euro Wallet');
        $wallet3 = $this->user->createWallet('GBP', 'Pound Wallet');
        
        $wallets = [$this->wallet, $wallet2, $wallet3];
        
        $result = $this->utils->bulkOperation($wallets, 'credit', ['amount' => 100]);
        
        expect($result)->toHaveKeys(['successful', 'failed', 'total']);
        expect($result['successful'])->toBe(3);
        expect($result['failed'])->toBe(0);
        expect($result['total'])->toBe(3);
    });

    it('can handle bulk operation failures', function () {
        $wallet2 = $this->user->createWallet('EUR', 'Euro Wallet');
        
        $wallets = [$this->wallet, $wallet2];
        
        // Try to debit more than available
        $result = $this->utils->bulkOperation($wallets, 'debit', ['amount' => 2000]);
        
        expect($result['successful'])->toBe(0);
        expect($result['failed'])->toBe(2);
        expect($result['errors'])->not->toBeEmpty();
    });
});

describe('Monitoring and Alerts', function () {
    it('can monitor wallet activity', function () {
        $monitoring = $this->utils->monitorWalletActivity($this->wallet);
        
        expect($monitoring)->toHaveKeys([
            'activity_level',
            'risk_score',
            'alerts',
            'recommendations'
        ]);
        
        expect($monitoring['activity_level'])->toBeIn(['low', 'medium', 'high']);
        expect($monitoring['risk_score'])->toBeNumeric();
    });

    it('can generate alerts for suspicious activity', function () {
        // Create suspicious pattern
        for ($i = 0; $i < 10; $i++) {
            $this->wallet->credit(1000);
            $this->wallet->debit(999);
        }
        
        $alerts = $this->utils->generateAlerts($this->wallet);
        
        expect($alerts)->toHaveKeys(['alerts', 'severity', 'recommendations']);
        expect($alerts['alerts'])->not->toBeEmpty();
    });
});

describe('Data Cleanup and Maintenance', function () {
    it('can clean up old data', function () {
        $result = $this->utils->cleanupOldData($this->wallet, ['days' => 90]);
        
        expect($result)->toHaveKeys(['cleaned', 'removed_count', 'size_freed']);
        expect($result['cleaned'])->toBeTrue();
    });

    it('can optimize wallet performance', function () {
        $result = $this->utils->optimizeWallet($this->wallet);
        
        expect($result)->toHaveKeys(['optimized', 'improvements', 'performance_gain']);
        expect($result['optimized'])->toBeTrue();
    });
});

describe('Integration and Compatibility', function () {
    it('can export data in different formats', function () {
        $jsonExport = $this->utils->exportWalletData($this->wallet, 'json');
        $csvExport = $this->utils->exportWalletData($this->wallet, 'csv');
        
        expect($jsonExport)->toBeArray();
        expect($csvExport)->toBeString();
    });

    it('can validate data integrity across operations', function () {
        $this->wallet->credit(100);
        $this->wallet->debit(50);
        
        $integrity = $this->utils->validateDataIntegrity($this->wallet);
        
        expect($integrity)->toHaveKeys(['valid', 'checksums', 'consistency']);
        expect($integrity['valid'])->toBeTrue();
    });
}); 