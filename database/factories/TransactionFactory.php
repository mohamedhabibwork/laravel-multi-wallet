<?php

namespace HWallet\LaravelMultiWallet\Database\Factories;

use HWallet\LaravelMultiWallet\Enums\TransactionType;
use HWallet\LaravelMultiWallet\Models\Transaction;
use HWallet\LaravelMultiWallet\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'wallet_id' => Wallet::factory(),
            'payable_type' => \HWallet\LaravelMultiWallet\Tests\Models\User::class,
            'payable_id' => \HWallet\LaravelMultiWallet\Tests\Models\User::factory(),
            'type' => $this->faker->randomElement([TransactionType::CREDIT->value, TransactionType::DEBIT->value]),
            'amount' => $this->faker->randomFloat(8, 1, 1000),
            'balance_type' => $this->faker->randomElement(['available', 'pending', 'frozen', 'trial']),
            'confirmed' => true,
            'meta' => [],
            'uuid' => $this->faker->uuid(),
        ];
    }

    public function credit(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TransactionType::CREDIT->value,
        ]);
    }

    public function debit(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TransactionType::DEBIT->value,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'confirmed' => false,
        ]);
    }
}
