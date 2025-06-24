<?php

namespace HWallet\LaravelMultiWallet\Database\Factories;

use HWallet\LaravelMultiWallet\Enums\TransferStatus;
use HWallet\LaravelMultiWallet\Models\Transfer;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransferFactory extends Factory
{
    protected $model = Transfer::class;

    public function definition(): array
    {
        return [
            'from_type' => \HWallet\LaravelMultiWallet\Tests\Models\User::class,
            'from_id' => \HWallet\LaravelMultiWallet\Tests\Models\User::factory(),
            'to_type' => \HWallet\LaravelMultiWallet\Tests\Models\User::class,
            'to_id' => \HWallet\LaravelMultiWallet\Tests\Models\User::factory(),
            'status' => TransferStatus::CONFIRMED->value,
            'fee' => 0.0,
            'discount' => 0.0,
            'status_last_changed_at' => now(),
            'uuid' => $this->faker->uuid,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TransferStatus::PENDING,
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TransferStatus::CONFIRMED,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TransferStatus::REJECTED,
        ]);
    }

    public function withFee(float $fee): static
    {
        return $this->state(fn (array $attributes) => [
            'fee' => $fee,
        ]);
    }

    public function withDiscount(float $discount): static
    {
        return $this->state(fn (array $attributes) => [
            'discount' => $discount,
        ]);
    }
}
