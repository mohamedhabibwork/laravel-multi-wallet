<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('multi-wallet.table_names.transactions'), function (Blueprint $table) {
            $table->id();
            $table->morphs('payable');

            // Handle foreign key for different database types
            if (config('database.default') === 'sqlite') {
                $table->unsignedBigInteger('wallet_id');
                $table->index('wallet_id');
            } else {
                $table->foreignId('wallet_id')->constrained(config('multi-wallet.table_names.wallets'));
            }

            $table->string('type'); // Changed from enum to string for compatibility
            $table->decimal('amount', 20, 8); // Use precision/scale that works across all database types
            $table->string('balance_type'); // Changed from enum to string for compatibility
            $table->boolean('confirmed')->default(false);

            // Handle JSON column for different database types
            if (config('database.default') === 'sqlite') {
                $table->text('meta')->nullable();
            } else {
                $table->json('meta')->nullable();
            }

            $table->string('uuid')->unique();

            // Handle timestamps for different database types
            if (config('database.default') === 'sqlite') {
                $table->timestamp('created_at')->useCurrent()->index();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
                $table->softDeletes()->index();
            } else {
                $table->timestampTz('created_at')->useCurrent()->index();
                $table->timestampTz('updated_at')->useCurrent()->useCurrentOnUpdate();
                $table->softDeletesTz()->index();
            }

            $table->index(['wallet_id', 'type']);
            $table->index(['wallet_id', 'balance_type']);
            $table->index(['wallet_id', 'confirmed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('multi-wallet.table_names.transactions'));
    }
};
