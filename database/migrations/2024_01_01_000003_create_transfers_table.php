<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('multi-wallet.table_names.transfers'), function (Blueprint $table) {
            $table->id();
            $table->morphs('from');
            $table->morphs('to');
            $table->string('status')->default('pending'); // Changed from enum to string
            $table->timestampTz('status_last_changed_at')->nullable();
            $table->foreignId('deposit_id')->nullable()->constrained(config('multi-wallet.table_names.transactions'));
            $table->foreignId('withdraw_id')->nullable()->constrained(config('multi-wallet.table_names.transactions'));
            $table->decimal('discount', 64, 8)->default(0);
            $table->decimal('fee', 64, 8)->default(0);
            $table->string('uuid')->unique();
            $table->timestampTz('created_at')->useCurrent()->index();
            $table->timestampTz('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->softDeletesTz()->index();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('multi-wallet.table_names.transfers'));
    }
};
