<?php

namespace HWallet\LaravelMultiWallet\Database\Factories;

use HWallet\LaravelMultiWallet\Models\Wallet;
use HWallet\LaravelMultiWallet\Tests\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class WalletFactory extends Factory
{
    protected $model = Wallet::class;

    public function definition(): array
    {
        return [
            'holder_type' => User::class,
            'holder_id' => User::factory(),
            'currency' => $this->faker->randomElement(['USD', 'EUR', 'GBP']),
            'name' => $this->faker->optional()->words(2, true),
            'slug' => fn (array $attributes) => Str::slug($attributes['name'] ?? $attributes['currency']),
            'description' => $this->faker->optional()->sentence(),
            'meta' => [],
            'balance_pending' => 0,
            'balance_available' => 0,
            'balance_frozen' => 0,
            'balance_trial' => 0,
        ];
    }

    public function withBalance(float $amount, string $balanceType = 'available'): static
    {
        return $this->state(function (array $attributes) use ($amount, $balanceType) {
            $balanceColumn = "balance_{$balanceType}";
            $attributes[$balanceColumn] = $amount;

            return $attributes;
        });
    }

    public function usd(): static
    {
        return $this->state(fn (array $attributes) => [
            'currency' => 'USD',
        ]);
    }

    public function eur(): static
    {
        return $this->state(fn (array $attributes) => [
            'currency' => 'EUR',
        ]);
    }
}
