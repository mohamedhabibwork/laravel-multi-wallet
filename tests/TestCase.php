<?php

namespace HWallet\LaravelMultiWallet\Tests;

use HWallet\LaravelMultiWallet\LaravelMultiWalletServiceProvider;
use HWallet\LaravelMultiWallet\Tests\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Run migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Create users table if it doesn't exist
        if (! \Schema::hasTable('users')) {
            \Schema::create('users', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password')->nullable();
                $table->rememberToken();
                $table->timestamps();
            });
        }
    }

    protected function getPackageProviders($app): array
    {
        return [
            LaravelMultiWalletServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Configure database
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Configure multi-wallet
        $app['config']->set('multi-wallet.default_currency', 'USD');
        $app['config']->set('multi-wallet.table_names.wallets', 'wallets');
        $app['config']->set('multi-wallet.table_names.transactions', 'transactions');
        $app['config']->set('multi-wallet.table_names.transfers', 'transfers');
        $app['config']->set('multi-wallet.audit_logging_enabled', false);
        $app['config']->set('multi-wallet.events.enabled', false);
    }

    protected function createUser(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }
}
