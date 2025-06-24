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
            $table->foreignId('wallet_id')->constrained(config('multi-wallet.table_names.wallets'));
            $table->string('type'); // Changed from enum to string
            $table->decimal('amount', 64, 8);
            $table->string('balance_type'); // Changed from enum to string
            $table->boolean('confirmed')->default(false);
            $table->json('meta')->nullable();
            $table->string('uuid')->unique();
            $table->timestampTz('created_at')->useCurrent()->index();
            $table->timestampTz('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->softDeletesTz()->index();

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
