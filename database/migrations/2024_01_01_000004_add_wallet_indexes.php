<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            // Composite index for holder lookup
            $table->index(['holder_type', 'holder_id'], 'idx_wallets_holder');
            
            // Index for currency searches
            $table->index('currency', 'idx_wallets_currency');
            
            // Index for slug searches
            $table->index('slug', 'idx_wallets_slug');
            
            // Composite index for holder and currency lookup
            $table->index(['holder_type', 'holder_id', 'currency'], 'idx_wallets_holder_currency');
            
            // Index for balance queries
            $table->index('balance_available', 'idx_wallets_balance_available');
            
            // Composite index for balance searches
            $table->index(['currency', 'balance_available'], 'idx_wallets_currency_balance');
            
            // Index for created_at for time-based queries
            $table->index('created_at', 'idx_wallets_created_at');
            
            // Index for updated_at for recent activity
            $table->index('updated_at', 'idx_wallets_updated_at');
        });

        Schema::table('transactions', function (Blueprint $table) {
            // Index for wallet_id (foreign key)
            $table->index('wallet_id', 'idx_transactions_wallet');
            
            // Index for transaction type
            $table->index('type', 'idx_transactions_type');
            
            // Index for balance type
            $table->index('balance_type', 'idx_transactions_balance_type');
            
            // Index for amount
            $table->index('amount', 'idx_transactions_amount');
            
            // Index for created_at for time-based queries
            $table->index('created_at', 'idx_transactions_created_at');
            
            // Composite index for wallet and time-based queries
            $table->index(['wallet_id', 'created_at'], 'idx_transactions_wallet_created');
            
            // Composite index for wallet and type
            $table->index(['wallet_id', 'type'], 'idx_transactions_wallet_type');
            
            // Index for UUID (if used)
            $table->index('uuid', 'idx_transactions_uuid');
        });

        Schema::table('transfers', function (Blueprint $table) {
            // Index for withdraw wallet
            $table->index('withdraw_id', 'idx_transfers_withdraw');
            
            // Index for deposit wallet
            $table->index('deposit_id', 'idx_transfers_deposit');
            
            // Index for status
            $table->index('status', 'idx_transfers_status');
            
            // Index for created_at
            $table->index('created_at', 'idx_transfers_created_at');
            
            // Index for updated_at
            $table->index('updated_at', 'idx_transfers_updated_at');
            
            // Composite index for wallet-based queries
            $table->index(['withdraw_id', 'status'], 'idx_transfers_withdraw_status');
            $table->index(['deposit_id', 'status'], 'idx_transfers_deposit_status');
            
            // Index for UUID
            $table->index('uuid', 'idx_transfers_uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->dropIndex('idx_wallets_holder');
            $table->dropIndex('idx_wallets_currency');
            $table->dropIndex('idx_wallets_slug');
            $table->dropIndex('idx_wallets_holder_currency');
            $table->dropIndex('idx_wallets_balance_available');
            $table->dropIndex('idx_wallets_currency_balance');
            $table->dropIndex('idx_wallets_created_at');
            $table->dropIndex('idx_wallets_updated_at');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('idx_transactions_wallet');
            $table->dropIndex('idx_transactions_type');
            $table->dropIndex('idx_transactions_balance_type');
            $table->dropIndex('idx_transactions_amount');
            $table->dropIndex('idx_transactions_created_at');
            $table->dropIndex('idx_transactions_wallet_created');
            $table->dropIndex('idx_transactions_wallet_type');
            $table->dropIndex('idx_transactions_uuid');
        });

        Schema::table('transfers', function (Blueprint $table) {
            $table->dropIndex('idx_transfers_withdraw');
            $table->dropIndex('idx_transfers_deposit');
            $table->dropIndex('idx_transfers_status');
            $table->dropIndex('idx_transfers_created_at');
            $table->dropIndex('idx_transfers_updated_at');
            $table->dropIndex('idx_transfers_withdraw_status');
            $table->dropIndex('idx_transfers_deposit_status');
            $table->dropIndex('idx_transfers_uuid');
        });
    }
}; 