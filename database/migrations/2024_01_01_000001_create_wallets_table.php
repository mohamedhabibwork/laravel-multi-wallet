<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('multi-wallet.table_names.wallets'), function (Blueprint $table) {
            $table->id();
            $table->morphs('holder');
            $table->string('currency', 3)->index();
            $table->string('name')->nullable();
            $table->string('slug');
            $table->text('description')->nullable();
            $table->json('meta')->nullable();
            $table->decimal('balance_pending', 64, 8)->default(0);
            $table->decimal('balance_available', 64, 8)->default(0);
            $table->decimal('balance_frozen', 64, 8)->default(0);
            $table->decimal('balance_trial', 64, 8)->default(0);
            $table->timestampTz('created_at')->useCurrent()->index();
            $table->timestampTz('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->softDeletesTz()->index();

            $table->index(['holder_type', 'holder_id', 'currency']);
            $table->unique(['slug', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('multi-wallet.table_names.wallets'));
    }
};
